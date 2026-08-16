<?php

namespace Tests\Feature\Launch;

use App\Domain\CommandCenter\Health\BusinessHealthService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\ExecutiveHealthTool;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\MrFoxActionProposal;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DemoDataSeederService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstCustomerAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_first_customer_journey(): void
    {
        $this->seed();

        // 1. New Customer Registration
        $user = User::factory()->create([
            'name' => 'John Founder',
            'email' => 'founder@acmecorp.com',
            'role' => 'company',
        ]);

        // 2. Automated Workspace & Trial Provisioning
        $provisioner = app(TenantProvisioningService::class);
        $provisioned = $provisioner->provision($user, [
            'company_name' => 'Acme Corporation',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
        ]);

        $org = $provisioned['organization'];
        $ws = $provisioned['workspace'];

        $this->actingAs($user)->withSession([
            'active_organization_id' => $org->id,
            'active_workspace_id' => $ws->id,
        ]);

        // 3. Onboarding Wizard Step Save
        $resStep = $this->post(route('onboarding.step'), [
            'step' => 1,
            'data' => [
                'company_name' => 'Acme Global Corporation',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
            ],
        ]);
        $resStep->assertStatus(200);

        // 4. Complete Onboarding
        $resComplete = $this->post(route('onboarding.complete'));
        $resComplete->assertStatus(200);
        $resComplete->assertJson(['success' => true]);

        // 5. CRM Pipeline & Lead Creation
        $pipeline = CrmPipeline::firstOrCreate([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Enterprise Pipeline',
        ], ['is_default' => true]);

        $stage = CrmStage::firstOrCreate([
            'pipeline_id' => $pipeline->id,
            'name' => 'Inbound Qualified',
        ], ['position' => 1]);

        $lead = CrmLead::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'name' => 'Enterprise Prospect Lead',
            'email' => 'prospect@megacorp.com',
            'status' => 'qualified',
        ]);
        $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, 'workspace_id' => $ws->id]);

        // 6. Product Creation
        $product = ProductServiceItem::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Cloud Implementation Package',
            'sku' => 'PKG-CLOUD-01',
            'type' => 'service',
            'quantity' => 10,
            'sale_price' => 5000.00,
        ]);
        $this->assertDatabaseHas('product_service_items', ['id' => $product->id]);

        // 7. Sales Invoice Creation
        $invoice = SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 'INV-FIRST-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 5000.00,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('sales_invoices', ['id' => $invoice->id]);

        // 8. Command Center Health Evaluation
        $healthService = app(BusinessHealthService::class);
        $health = $healthService->evaluateHealth($user, $ws);
        $this->assertEquals(100, $health['overall']->score);

        // 9. Mr. Fox Executive Intelligence Tool
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $ws);
        $healthTool = app(ExecutiveHealthTool::class);
        $toolResult = $healthTool->execute($context, []);
        $this->assertTrue($toolResult->success);

        // 10. Load Demo Data
        $demoSeeder = app(DemoDataSeederService::class);
        $demoRes = $demoSeeder->seedDemoData($user, $ws);
        $this->assertGreaterThan(0, $demoRes['invoices_count']);

        // 11. Action Proposal & Approval Lifecycle
        $proposal = MrFoxActionProposal::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'tool_name' => 'crm.create.lead',
            'payload' => ['name' => 'Automated Partner Lead'],
            'human_summary' => 'Create partner lead from AI insight',
            'risk_level' => 'HIGH',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $resReject = $this->post(route('command-center.approvals.reject', ['id' => $proposal->id]));
        $resReject->assertStatus(200);
        $this->assertEquals('rejected', $proposal->fresh()->status);

        // 12. Reset Demo Data
        $deletedCount = $demoSeeder->resetDemoData($ws);
        $this->assertGreaterThan(0, $deletedCount);
    }
}
