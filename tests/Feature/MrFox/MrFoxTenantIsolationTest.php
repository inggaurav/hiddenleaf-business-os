<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\CrmSearchLeadsTool;
use App\Domain\MrFox\Tools\InventoryLowStockTool;
use App\Domain\MrFox\Tools\SalesOutstandingSummaryTool;
use App\Models\CrmLead;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_isolation_is_strictly_enforced_across_tools(): void
    {
        $this->seed();

        // Tenant A
        $userA = User::factory()->create(['role' => 'company_admin']);
        $planA = Plan::create(['name' => 'Plan A', 'modules' => ['crm', 'account', 'productservice'], 'status' => true, 'created_by' => $userA->id]);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id, 'plan_id' => $planA->id, 'name' => 'Org A']);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $userA->id, 'name' => 'Workspace A']);
        $orgA->members()->attach($userA, ['role' => 'owner']);
        $wsA->members()->attach($userA);

        // Tenant B
        $userB = User::factory()->create(['role' => 'company_admin']);
        $planB = Plan::create(['name' => 'Plan B', 'modules' => ['crm', 'account', 'productservice'], 'status' => true, 'created_by' => $userB->id]);
        $orgB = Organization::factory()->create(['owner_id' => $userB->id, 'plan_id' => $planB->id, 'name' => 'Org B']);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'created_by' => $userB->id, 'name' => 'Workspace B']);
        $orgB->members()->attach($userB, ['role' => 'owner']);
        $wsB->members()->attach($userB);

        $pipeA = \App\Models\CrmPipeline::create(['organization_id' => $orgA->id, 'workspace_id' => $wsA->id, 'name' => 'Pipeline A']);
        $stageA = \App\Models\CrmStage::create(['pipeline_id' => $pipeA->id, 'name' => 'Stage A', 'position' => 0]);

        // Create confidential records for Tenant A
        CrmLead::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'pipeline_id' => $pipeA->id,
            'stage_id' => $stageA->id,
            'name' => 'Secret Lead A',
            'company' => 'Top Secret Corp',
            'estimated_value' => 999999,
        ]);

        SalesInvoice::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'customer_id' => 1,
            'invoice_id' => 'INV-CONFIDENTIAL-A',
            'issue_date' => now()->subDays(10),
            'due_date' => now()->subDays(5),
            'status' => 1,
            'total_amount' => 50000,
        ]);

        \App\Models\ProductServiceItem::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'name' => 'Proprietary Widget A',
            'sku' => 'SECRET-01',
            'type' => 'product',
            'reorder_level' => 10,
        ]);

        // Context for User B
        $contextService = app(BusinessContextService::class);
        $contextB = $contextService->createToolContext($userB, $wsB);

        // 1. CRM Leads Tool executed in Context B
        $crmTool = new CrmSearchLeadsTool();
        $crmResult = $crmTool->execute($contextB, ['query' => 'Secret']);
        $this->assertEquals(0, count($crmResult->data));
        $this->assertStringNotContainsString('Secret Lead A', $crmResult->summary);

        // 2. Sales Outstanding Tool executed in Context B
        $salesTool = new SalesOutstandingSummaryTool();
        $salesResult = $salesTool->execute($contextB, []);
        $this->assertEquals(0, $salesResult->data['total_open_invoices']);
        $this->assertEquals(0, $salesResult->data['total_receivables']);

        // 3. Inventory Low Stock Tool executed in Context B
        $inventoryTool = new InventoryLowStockTool();
        $inventoryResult = $inventoryTool->execute($contextB, []);
        $this->assertEquals(0, count($inventoryResult->data));
    }
}
