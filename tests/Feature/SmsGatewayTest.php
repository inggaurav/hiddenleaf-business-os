<?php

namespace Tests\Feature;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\RiskLevel;
use App\Models\AccountCustomer;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use HiddenLeaf\SmsNotifications\Domain\Gateways\Drivers\FakeSmsDriver;
use HiddenLeaf\SmsNotifications\Domain\Gateways\SmsGatewayRouter;
use HiddenLeaf\SmsNotifications\Domain\Manual\SmsManualSendService;
use HiddenLeaf\SmsNotifications\Domain\MrFox\Tools\SendSmsToCustomerTool;
use HiddenLeaf\SmsNotifications\Domain\MrFox\Tools\SmsLogSummaryTool;
use HiddenLeaf\SmsNotifications\Domain\Templates\SmsTemplateRenderer;
use HiddenLeaf\SmsNotifications\Domain\Triggers\SmsTriggerService;
use HiddenLeaf\SmsNotifications\Models\SmsGateway;
use HiddenLeaf\SmsNotifications\Models\SmsLog;
use HiddenLeaf\SmsNotifications\Models\SmsTemplate;
use HiddenLeaf\SmsNotifications\Models\SmsTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $org;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        FakeSmsDriver::reset();

        $plan = Plan::create([
            'name' => 'Enterprise Plan',
            'modules' => ['sms-notifications', 'crm', 'hrm'],
            'price_per_user_monthly' => 0,
            'price_per_user_yearly' => 0,
            'price_per_storage_monthly' => 0,
            'price_per_storage_yearly' => 0,
            'number_of_users' => 100,
            'storage_limit' => 100,
            'workspace_limit' => 10,
            'status' => 1,
            'created_by' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@hiddenleaf.test',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'type' => 'super_admin',
        ]);

        $this->org = Organization::create([
            'name' => 'HiddenLeaf Org',
            'slug' => 'hiddenleaf-org',
            'owner_id' => $this->user->id,
            'plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $this->workspace = Workspace::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Workspace',
            'slug' => 'main-workspace',
            'created_by' => $this->user->id,
            'is_active' => true,
        ]);

        \App\Models\UserActiveModule::create([
            'workspace_id' => $this->workspace->id,
            'module_name' => 'sms-notifications',
            'is_active' => true,
        ]);
    }

    public function test_gateway_sends_via_fake_driver_and_confirms_sms_logs_row_created(): void
    {
        $gateway = new SmsGateway();
        $gateway->workspace_id = $this->workspace->id;
        $gateway->name = 'Test Fake Gateway';
        $gateway->driver = 'fake';
        $gateway->setEncryptedCredentialsAttribute(['dummy' => 'value']);
        $gateway->is_default = true;
        $gateway->is_active = true;
        $gateway->save();

        $router = app(SmsGatewayRouter::class);
        $resolved = $router->resolveForWorkspace($this->workspace);

        $this->assertSame('fake', $resolved['driver']);

        $to = '+91 98765 43210';
        $body = 'Hello, this is a test SMS verification message.';
        $result = $resolved['instance']->send($to, $body);

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->messageId);

        // Record in sms_logs
        $log = SmsLog::create([
            'workspace_id' => $this->workspace->id,
            'to_number' => $to,
            'to_name' => 'Test Recipient',
            'body_sent' => $body,
            'gateway_driver' => $resolved['driver'],
            'status' => 'sent',
            'triggered_by' => 'manual',
            'cost_units' => $result->costUnits,
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'id' => $log->id,
            'workspace_id' => $this->workspace->id,
            'to_number' => $to,
            'status' => 'sent',
            'gateway_driver' => 'fake',
        ]);

        $this->assertCount(1, FakeSmsDriver::$sentMessages);
        $this->assertSame($to, FakeSmsDriver::$sentMessages[0]['to']);
        $this->assertSame($body, FakeSmsDriver::$sentMessages[0]['body']);
    }

    public function test_template_renderer_replaces_variables_and_calculates_segments(): void
    {
        $renderer = app(SmsTemplateRenderer::class);

        $templateText = 'Dear {{customer_name}}, your invoice {{invoice_number}} of {{invoice_amount}} is due on {{due_date}}. Thank you, {{workspace_name}}.';
        $data = [
            'customer_name' => 'Vikram Malhotra',
            'invoice_number' => 'INV-2026-99',
            'invoice_amount' => '₹45,000',
            'due_date' => '15 October 2026',
            'workspace_name' => 'Apex Agency',
        ];

        $rendered = $renderer->render($templateText, $data);

        $this->assertStringContainsString('Vikram Malhotra', $rendered);
        $this->assertStringContainsString('INV-2026-99', $rendered);
        $this->assertStringContainsString('₹45,000', $rendered);
        $this->assertStringContainsString('15 October 2026', $rendered);
        $this->assertStringContainsString('Apex Agency', $rendered);

        // Segment calculation
        $shortAnalysis = $renderer->analyzeLength('Short message within 160 characters.');
        $this->assertTrue($shortAnalysis['is_single_segment']);
        $this->assertSame(1, $shortAnalysis['segments']);
        $this->assertNull($shortAnalysis['warning']);

        $longMessage = str_repeat('A', 170);
        $longAnalysis = $renderer->analyzeLength($longMessage);
        $this->assertFalse($longAnalysis['is_single_segment']);
        $this->assertSame(2, $longAnalysis['segments']);
        $this->assertNotNull($longAnalysis['warning']);
    }

    public function test_template_renderer_rejects_unknown_variables(): void
    {
        $renderer = app(SmsTemplateRenderer::class);

        $this->expectException(InvalidArgumentException::class);
        $renderer->validateAndExtractVariables('Hello {{unknown_variable_xyz}}, your balance is low.');
    }

    public function test_sms_trigger_service_dispatches_matching_event_and_logs(): void
    {
        // Configure gateway
        $gateway = new SmsGateway();
        $gateway->workspace_id = $this->workspace->id;
        $gateway->name = 'Fake Default';
        $gateway->driver = 'fake';
        $gateway->setEncryptedCredentialsAttribute([]);
        $gateway->is_default = true;
        $gateway->is_active = true;
        $gateway->save();

        // Create template
        $template = SmsTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Invoice Paid Alert',
            'body' => 'Payment received for invoice {{invoice_number}} of {{invoice_amount}}! Thanks {{customer_name}}.',
            'variables' => ['invoice_number', 'invoice_amount', 'customer_name'],
            'is_active' => true,
        ]);

        // Create trigger
        SmsTrigger::create([
            'workspace_id' => $this->workspace->id,
            'template_id' => $template->id,
            'event_name' => 'invoice.paid',
            'is_active' => true,
        ]);

        $triggerService = app(SmsTriggerService::class);
        $logs = $triggerService->handleEvent($this->workspace, 'invoice.paid', [
            'customer_phone' => '+91 99887 76655',
            'customer_name' => 'Neha Sharma',
            'invoice_number' => 'INV-888',
            'invoice_amount' => '₹12,000',
            'id' => 888,
        ]);

        $this->assertCount(1, $logs);
        $this->assertSame('sent', $logs[0]->status);
        $this->assertSame('+91 99887 76655', $logs[0]->to_number);
        $this->assertStringContainsString('INV-888', $logs[0]->body_sent);
        $this->assertStringContainsString('Neha Sharma', $logs[0]->body_sent);

        $this->assertDatabaseHas('sms_logs', [
            'id' => $logs[0]->id,
            'workspace_id' => $this->workspace->id,
            'triggered_by' => 'event',
            'status' => 'sent',
        ]);
    }

    public function test_manual_send_service_dispatches_to_customer_and_logs(): void
    {
        $gateway = new SmsGateway();
        $gateway->workspace_id = $this->workspace->id;
        $gateway->name = 'Fake Default';
        $gateway->driver = 'fake';
        $gateway->setEncryptedCredentialsAttribute([]);
        $gateway->is_default = true;
        $gateway->is_active = true;
        $gateway->save();

        $customer = AccountCustomer::create([
            'organization_id' => $this->org->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Rahul Verma',
            'contact' => '+91 91234 56789',
            'email' => 'rahul@example.com',
            'created_by' => $this->user->id,
        ]);

        $sendService = app(SmsManualSendService::class);
        $log = $sendService->sendToCustomer(
            customer: $customer,
            body: 'Hello {{customer_name}}, this is an important message from {{workspace_name}}.',
            actor: $this->user,
            workspace: $this->workspace
        );

        $this->assertSame('sent', $log->status);
        $this->assertSame('+91 91234 56789', $log->to_number);
        $this->assertStringContainsString('Rahul Verma', $log->body_sent);
        $this->assertStringContainsString('Main Workspace', $log->body_sent);

        $this->assertDatabaseHas('sms_logs', [
            'id' => $log->id,
            'related_id' => $customer->id,
            'triggered_by' => 'manual',
        ]);
    }

    public function test_mr_fox_tools_have_correct_risk_level_and_execution(): void
    {
        // Configure gateway
        $gateway = new SmsGateway();
        $gateway->workspace_id = $this->workspace->id;
        $gateway->name = 'Fake Default';
        $gateway->driver = 'fake';
        $gateway->setEncryptedCredentialsAttribute([]);
        $gateway->is_default = true;
        $gateway->is_active = true;
        $gateway->save();

        $customer = AccountCustomer::create([
            'organization_id' => $this->org->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Ananya Rao',
            'contact' => '+91 90000 11111',
            'email' => 'ananya@example.com',
            'created_by' => $this->user->id,
        ]);

        // Verify SendSmsToCustomerTool
        $sendTool = app(SendSmsToCustomerTool::class);
        $this->assertSame('send_sms_to_customer', $sendTool->name());
        $this->assertSame(RiskLevel::HIGH, $sendTool->riskLevel());
        $this->assertTrue($sendTool->riskLevel()->requiresApproval());

        $context = new ToolContext(
            user: $this->user,
            organization: $this->org,
            workspace: $this->workspace,
        );

        // Preview execution
        $previewResult = $sendTool->execute($context, [
            'customer_id' => $customer->id,
            'body' => 'Hello {{customer_name}}, your order is ready.',
            'preview_first' => true,
        ]);
        $this->assertTrue($previewResult->success);
        $this->assertSame('preview', $previewResult->data['mode']);
        $this->assertStringContainsString('Ananya Rao', $previewResult->data['rendered_body']);

        // Live execution
        $liveResult = $sendTool->execute($context, [
            'customer_id' => $customer->id,
            'body' => 'Hello {{customer_name}}, your order is ready.',
            'preview_first' => false,
        ]);
        $this->assertTrue($liveResult->success);
        $this->assertSame('dispatched', $liveResult->data['mode']);

        // Verify SmsLogSummaryTool
        $summaryTool = app(SmsLogSummaryTool::class);
        $this->assertSame('sms_log_summary', $summaryTool->name());
        $this->assertSame(RiskLevel::READ, $summaryTool->riskLevel());

        $summaryResult = $summaryTool->execute($context, []);
        $this->assertTrue($summaryResult->success);
        $this->assertGreaterThanOrEqual(1, $summaryResult->data['sent_today']);
    }

    public function test_http_controller_endpoints(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession([
                'active_workspace_id' => $this->workspace->id,
                'active_organization_id' => $this->org->id,
            ])
            ->get('/sms');

        $response->assertStatus(200);

        // Store a gateway via HTTP
        $storeResp = $this->actingAs($this->user)
            ->withSession([
                'active_workspace_id' => $this->workspace->id,
                'active_organization_id' => $this->org->id,
            ])
            ->post('/sms/gateways', [
                'name' => 'API MSG91 Gateway',
                'driver' => 'msg91',
                'credentials' => [
                    'authkey' => 'secret_msg91_key',
                    'sender_id' => 'MYCORP',
                    'route' => '4',
                ],
                'is_default' => true,
            ]);

        $storeResp->assertSessionHas('success');
        $this->assertDatabaseHas('sms_gateways', [
            'workspace_id' => $this->workspace->id,
            'name' => 'API MSG91 Gateway',
            'driver' => 'msg91',
            'is_default' => true,
        ]);
    }
}
