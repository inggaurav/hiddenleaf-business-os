<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $org;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Full OS', 'modules' => ['crm', 'account', 'productservice', 'taskly', 'hrm', 'pos', 'lead'], 'status' => true, 'created_by' => $this->user->id]);
        $this->org = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id, 'name' => 'Acme Agency']);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'name' => 'Main Workspace']);

        $this->org->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'crm']);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);

        $pipeline = CrmPipeline::create([
            'organization_id' => $this->org->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Acme Pipeline',
            'is_default' => true,
        ]);
        CrmStage::create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Inbound',
            'position' => 0,
            'probability' => 20,
        ]);
    }

    public function test_tool_registry_discovers_all_registered_tools(): void
    {
        $registry = app(MrFoxToolRegistry::class);
        $tools = $registry->all();

        $this->assertArrayHasKey('business.dashboard.summary', $tools);
        $this->assertArrayHasKey('crm.search.leads', $tools);
        $this->assertArrayHasKey('sales.outstanding.summary', $tools);
        $this->assertArrayHasKey('inventory.low_stock', $tools);
        $this->assertArrayHasKey('taskly.overdue.tasks', $tools);
        $this->assertArrayHasKey('hr.employee.summary', $tools);
    }

    public function test_mr_fox_chat_endpoint_processes_messages_and_tools(): void
    {
        $pipeline = CrmPipeline::where('workspace_id', $this->workspace->id)->first();
        $stage = CrmStage::where('pipeline_id', $pipeline->id)->first();

        CrmLead::create([
            'organization_id' => $this->org->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'name' => 'Enterprise Prospect Alpha',
            'company' => 'Alpha Global',
            'estimated_value' => 50000,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/mr-fox/chat', [
            'message' => 'Check CRM leads in our pipeline',
            'active_page' => 'CRM Leads',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'reply',
                'provider',
                'model',
                'tools_executed',
                'evidence',
            ]);

        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertNotEmpty($json['tools_executed']);
    }

    public function test_mr_fox_insights_endpoint_returns_grounded_signals_and_evidence(): void
    {
        SalesInvoice::create([
            'organization_id' => $this->org->id,
            'workspace_id' => $this->workspace->id,
            'customer_id' => 1,
            'invoice_id' => 'INV-TEST-001',
            'issue_date' => now()->subDays(30),
            'due_date' => now()->subDays(10),
            'status' => 'posted',
            'total_amount' => 1000,
        ]);

        // Create low stock product
        ProductServiceItem::create([
            'organization_id' => $this->org->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Server Hardware Chassis',
            'sku' => 'SKU-SRV-01',
            'type' => 'product',
            'purchase_price' => 200,
            'sale_price' => 350,
            'reorder_level' => 10,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/mr-fox/insights');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'insights' => [
                    '*' => ['id', 'category', 'severity', 'title', 'summary', 'evidence', 'suggested_action'],
                ],
            ]);

        $insights = $response->json('insights');
        $this->assertNotEmpty($insights);

        // Verify overdue receivables signal exists
        $receivables = collect($insights)->firstWhere('id', 'insight_receivables_overdue');
        $this->assertNotNull($receivables);
        $this->assertNotEmpty($receivables['evidence']);
        $this->assertEquals('INV-TEST-001', $receivables['evidence'][0]['label']);
    }
}
