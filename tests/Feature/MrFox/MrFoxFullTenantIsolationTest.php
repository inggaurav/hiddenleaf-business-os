<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\AccountingCashPositionTool;
use App\Domain\MrFox\Tools\AccountingPnlTool;
use App\Domain\MrFox\Tools\BusinessDashboardSummaryTool;
use App\Domain\MrFox\Tools\CrmGetLeadTool;
use App\Domain\MrFox\Tools\CrmPipelineSummaryTool;
use App\Domain\MrFox\Tools\CrmSearchLeadsTool;
use App\Domain\MrFox\Tools\HrAttendanceSummaryTool;
use App\Domain\MrFox\Tools\HrEmployeeSummaryTool;
use App\Domain\MrFox\Tools\HrPendingLeaveTool;
use App\Domain\MrFox\Tools\InventoryLowStockTool;
use App\Domain\MrFox\Tools\InventoryStockSummaryTool;
use App\Domain\MrFox\Tools\PurchaseBillSearchTool;
use App\Domain\MrFox\Tools\PurchasePayablesSummaryTool;
use App\Domain\MrFox\Tools\SalesInvoiceSearchTool;
use App\Domain\MrFox\Tools\SalesOutstandingSummaryTool;
use App\Domain\MrFox\Tools\TasklyOverdueTasksTool;
use App\Domain\MrFox\Tools\TasklySearchProjectsTool;
use App\Models\AccountType;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxFullTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Organization $orgA;
    private Workspace $wsA;

    private User $userB;
    private Organization $orgB;
    private Workspace $wsB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Tenant A
        $this->userA = User::factory()->create(['role' => 'super_admin']);
        $planA = Plan::create(['name' => 'Plan A', 'modules' => ['crm', 'account', 'productservice', 'taskly', 'hrm'], 'status' => true, 'created_by' => $this->userA->id]);
        $this->orgA = Organization::factory()->create(['owner_id' => $this->userA->id, 'plan_id' => $planA->id, 'name' => 'Org A']);
        $this->wsA = Workspace::factory()->create(['organization_id' => $this->orgA->id, 'created_by' => $this->userA->id, 'name' => 'Workspace A']);
        $this->orgA->members()->attach($this->userA, ['role' => 'owner']);
        $this->wsA->members()->attach($this->userA);

        // Tenant B
        $this->userB = User::factory()->create(['role' => 'super_admin']);
        $planB = Plan::create(['name' => 'Plan B', 'modules' => ['crm', 'account', 'productservice', 'taskly', 'hrm'], 'status' => true, 'created_by' => $this->userB->id]);
        $this->orgB = Organization::factory()->create(['owner_id' => $this->userB->id, 'plan_id' => $planB->id, 'name' => 'Org B']);
        $this->wsB = Workspace::factory()->create(['organization_id' => $this->orgB->id, 'created_by' => $this->userB->id, 'name' => 'Workspace B']);
        $this->orgB->members()->attach($this->userB, ['role' => 'owner']);
        $this->wsB->members()->attach($this->userB);

        // Populate confidential records for Tenant A ONLY
        $pipeA = CrmPipeline::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'name' => 'Alpha Pipeline']);
        $stageA = CrmStage::create(['pipeline_id' => $pipeA->id, 'name' => 'Alpha Stage', 'position' => 0]);
        $leadA = CrmLead::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'pipeline_id' => $pipeA->id, 'stage_id' => $stageA->id, 'name' => 'Alpha Secret Lead', 'estimated_value' => 75000]);
        CrmDeal::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'lead_id' => $leadA->id, 'pipeline_id' => $pipeA->id, 'stage_id' => $stageA->id, 'name' => 'Alpha Secret Deal', 'value' => 75000, 'status' => 'open']);

        SalesInvoice::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'customer_id' => 1, 'invoice_id' => 'INV-ALPHA-01', 'status' => 1, 'total_amount' => 33000, 'due_date' => now()->subDays(5)]);
        PurchaseInvoice::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'vendor_id' => 1, 'invoice_id' => 'BILL-ALPHA-01', 'status' => 1, 'total_amount' => 12000, 'due_date' => now()->subDays(3)]);

        $whA = Warehouse::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'name' => 'Alpha Warehouse']);
        $prodA = ProductServiceItem::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'name' => 'Alpha Secret Part', 'sku' => 'ALPHA-001', 'type' => 'product', 'sale_price' => 500, 'purchase_price' => 300, 'reorder_level' => 15]);
        WarehouseStock::create(['warehouse_id' => $whA->id, 'product_id' => $prodA->id, 'quantity' => 10]);

        $projA = TasklyProject::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'name' => 'Project Classified Alpha', 'status' => 'ongoing']);
        $tStageA = TasklyStage::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'project_id' => $projA->id, 'name' => 'To Do', 'order' => 0]);
        TasklyTask::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'project_id' => $projA->id, 'stage_id' => $tStageA->id, 'title' => 'Secret Alpha Deliverable', 'due_on' => now()->subDays(2)]);

        $empA = HrEmployee::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'employee_number' => 'EMP-001', 'name' => 'John Doe Alpha', 'email' => 'john.alpha@example.com', 'basic_salary' => 8500, 'joined_at' => today()]);
        HrAttendance::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'employee_id' => $empA->id, 'attendance_date' => today(), 'status' => 'present']);
        $leaveTypeA = HrLeaveType::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'name' => 'Annual Leave']);
        HrLeaveRequest::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'employee_id' => $empA->id, 'leave_type_id' => $leaveTypeA->id, 'starts_on' => now(), 'ends_on' => now()->addDays(3), 'days' => 3, 'status' => 'pending']);

        $accType = AccountType::firstOrCreate([
            'organization_id' => $this->orgA->id,
            'workspace_id' => $this->wsA->id,
            'name' => 'Bank Accounts',
        ], [
            'classification' => 'asset',
            'normal_balance' => 'debit',
        ]);
        LedgerAccount::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'account_type_id' => $accType->id, 'name' => 'Alpha Bank', 'code' => '1010', 'is_bank' => true, 'opening_balance' => 95000]);
    }

    public function test_all_tools_strictly_isolate_tenant_b_from_tenant_a_data(): void
    {
        $contextService = app(BusinessContextService::class);
        $contextB = $contextService->createToolContext($this->userB, $this->wsB);

        // 1. Executive Dashboard
        $dashTool = new BusinessDashboardSummaryTool();
        $dashRes = $dashTool->execute($contextB, []);
        $this->assertEquals(0, $dashRes->data['total_sales']);
        $this->assertEquals(0, $dashRes->data['total_expenses']);
        $this->assertEquals(0, $dashRes->data['open_leads']);

        // 2. CRM Leads & Deals
        $crmSearch = new CrmSearchLeadsTool();
        $crmRes = $crmSearch->execute($contextB, ['query' => 'Alpha']);
        $this->assertEmpty($crmRes->data);

        $crmGet = new CrmGetLeadTool();
        $crmGetRes = $crmGet->execute($contextB, ['lead_id' => 1]);
        $this->assertFalse($crmGetRes->success);

        $crmPipe = new CrmPipelineSummaryTool();
        $crmPipeRes = $crmPipe->execute($contextB, []);
        $this->assertEquals(0, $crmPipeRes->data['open_deals_count']);
        $this->assertEquals(0, $crmPipeRes->data['pipeline_value']);

        // 3. Sales & Receivables
        $salesSearch = new SalesInvoiceSearchTool();
        $salesRes = $salesSearch->execute($contextB, ['query' => 'ALPHA']);
        $this->assertEmpty($salesRes->data);

        $salesOut = new SalesOutstandingSummaryTool();
        $salesOutRes = $salesOut->execute($contextB, []);
        $this->assertEquals(0, $salesOutRes->data['total_receivables']);

        // 4. Purchases & Payables
        $purchSearch = new PurchaseBillSearchTool();
        $purchRes = $purchSearch->execute($contextB, ['query' => 'ALPHA']);
        $this->assertEmpty($purchRes->data);

        $purchPay = new PurchasePayablesSummaryTool();
        $purchPayRes = $purchPay->execute($contextB, []);
        $this->assertEquals(0, $purchPayRes->data['total_payables']);

        // 5. Accounting PnL & Cash Position
        $pnlTool = new AccountingPnlTool();
        $pnlRes = $pnlTool->execute($contextB, []);
        $this->assertEquals(0, $pnlRes->data['gross_revenue']);
        $this->assertEquals(0, $pnlRes->data['total_expenses']);

        $cashTool = new AccountingCashPositionTool();
        $cashRes = $cashTool->execute($contextB, []);
        $this->assertEmpty($cashRes->data);

        // 6. Inventory
        $invStock = new InventoryStockSummaryTool();
        $invStockRes = $invStock->execute($contextB, []);
        $this->assertEquals(0, $invStockRes->data['total_sku_count']);
        $this->assertEquals(0, $invStockRes->data['total_units_on_hand']);

        $invLow = new InventoryLowStockTool();
        $invLowRes = $invLow->execute($contextB, []);
        $this->assertEmpty($invLowRes->data);

        // 7. Taskly Projects & Tasks
        $tasklyProj = new TasklySearchProjectsTool();
        $tasklyProjRes = $tasklyProj->execute($contextB, ['query' => 'Alpha']);
        $this->assertEmpty($tasklyProjRes->data);

        $tasklyOverdue = new TasklyOverdueTasksTool();
        $tasklyOverdueRes = $tasklyOverdue->execute($contextB, []);
        $this->assertEmpty($tasklyOverdueRes->data);

        // 8. HRM
        $hrEmp = new HrEmployeeSummaryTool();
        $hrEmpRes = $hrEmp->execute($contextB, []);
        $this->assertEquals(0, $hrEmpRes->data['total_headcount']);

        $hrAtt = new HrAttendanceSummaryTool();
        $hrAttRes = $hrAtt->execute($contextB, []);
        $this->assertEquals(0, $hrAttRes->data['total_employees']);

        $hrLeave = new HrPendingLeaveTool();
        $hrLeaveRes = $hrLeave->execute($contextB, []);
        $this->assertEmpty($hrLeaveRes->data);
    }
}
