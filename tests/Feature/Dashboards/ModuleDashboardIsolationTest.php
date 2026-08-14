<?php

namespace Tests\Feature\Dashboards;

use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PosOrder;
use App\Models\PosRegister;
use App\Models\PosSession;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesProposal;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleDashboardIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $tenantAUser;
    protected Organization $tenantAOrg;
    protected Workspace $tenantAWs;

    protected User $tenantBUser;
    protected Organization $tenantBOrg;
    protected Workspace $tenantBWs;

    protected function setUp(): void
    {
        parent::setUp();

        $modules = ['account', 'hrm', 'lead', 'taskly', 'pos', 'productservice', 'sales', 'procurement'];

        // Tenant A Setup
        $this->tenantAUser = User::factory()->create(['name' => 'Tenant A Admin', 'role' => 'company_admin']);
        $planA = Plan::create(['name' => 'Enterprise A', 'status' => true, 'modules' => $modules, 'created_by' => $this->tenantAUser->id]);
        $this->tenantAOrg = Organization::factory()->create(['owner_id' => $this->tenantAUser->id, 'plan_id' => $planA->id]);
        $this->tenantAWs = Workspace::factory()->create(['organization_id' => $this->tenantAOrg->id, 'created_by' => $this->tenantAUser->id]);
        $this->tenantAOrg->members()->attach($this->tenantAUser, ['role' => 'owner']);
        $this->tenantAWs->members()->attach($this->tenantAUser);

        foreach ($modules as $mod) {
            UserActiveModule::create(['workspace_id' => $this->tenantAWs->id, 'module_name' => $mod]);
        }

        // Tenant B Setup
        $this->tenantBUser = User::factory()->create(['name' => 'Tenant B Admin', 'role' => 'company_admin']);
        $planB = Plan::create(['name' => 'Enterprise B', 'status' => true, 'modules' => $modules, 'created_by' => $this->tenantBUser->id]);
        $this->tenantBOrg = Organization::factory()->create(['owner_id' => $this->tenantBUser->id, 'plan_id' => $planB->id]);
        $this->tenantBWs = Workspace::factory()->create(['organization_id' => $this->tenantBOrg->id, 'created_by' => $this->tenantBUser->id]);
        $this->tenantBOrg->members()->attach($this->tenantBUser, ['role' => 'owner']);
        $this->tenantBWs->members()->attach($this->tenantBUser);

        foreach ($modules as $mod) {
            UserActiveModule::create(['workspace_id' => $this->tenantBWs->id, 'module_name' => $mod]);
        }
    }

    public function test_accounting_dashboard_strictly_isolates_metrics_and_transactions(): void
    {
        // Populate Tenant A Data
        SalesInvoice::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'invoice_id' => 101,
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 1000,
            'created_by' => $this->tenantAUser->id,
        ]);

        PurchaseInvoice::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'invoice_id' => 201,
            'status' => 'paid',
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 400,
            'created_by' => $this->tenantAUser->id,
        ]);

        // Populate Tenant B Data
        SalesInvoice::create([
            'organization_id' => $this->tenantBOrg->id,
            'workspace_id' => $this->tenantBWs->id,
            'invoice_id' => 999,
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 50000,
            'created_by' => $this->tenantBUser->id,
        ]);

        // Tenant A request
        $responseA = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/accounting/dashboard');

        $responseA->assertOk();
        $responseA->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Dashboard')
            ->where('stats.total_clients', 1)
            ->where('stats.total_vendors', 1)
            ->where('stats.total_customer_payment', 1000)
            ->where('stats.total_vendor_payment', 400)
            ->where('stats.net_profit', 600)
            ->has('recentRevenues', 1)
            ->where('recentRevenues.0.title', '101')
            ->has('recentExpenses', 1)
            ->where('recentExpenses.0.title', '201'));

        // Tenant B request
        $responseB = $this->actingAs($this->tenantBUser)
            ->withSession(['active_organization_id' => $this->tenantBOrg->id, 'active_workspace_id' => $this->tenantBWs->id])
            ->get('/accounting/dashboard');

        $responseB->assertOk();
        $responseB->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Dashboard')
            ->where('stats.total_clients', 1)
            ->where('stats.total_customer_payment', 50000)
            ->where('stats.total_vendor_payment', 0)
            ->has('recentRevenues', 1)
            ->where('recentRevenues.0.title', '999'));
    }

    public function test_hrm_dashboard_calculates_daily_attendance_and_leaves(): void
    {
        $empA = HrEmployee::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Alice Engineer',
            'employee_number' => 'EMP-001',
            'email' => 'alice@company.local',
            'status' => 'active',
            'basic_salary' => 5000,
            'joined_at' => now()->subMonths(3)->toDateString(),
            'created_by' => $this->tenantAUser->id,
        ]);

        $empA2 = HrEmployee::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Bob Designer',
            'employee_number' => 'EMP-002',
            'email' => 'bob@company.local',
            'status' => 'active',
            'basic_salary' => 4500,
            'joined_at' => now()->subMonths(1)->toDateString(),
            'created_by' => $this->tenantAUser->id,
        ]);

        HrAttendance::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'employee_id' => $empA->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'clock_in' => '09:00:00',
            'created_by' => $this->tenantAUser->id,
        ]);

        HrAttendance::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'employee_id' => $empA2->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
            'created_by' => $this->tenantAUser->id,
        ]);

        $leaveType = HrLeaveType::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Annual Leave',
            'days_per_year' => 20,
            'created_by' => $this->tenantAUser->id,
        ]);

        HrLeaveRequest::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'employee_id' => $empA->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
            'days' => 3,
            'reason' => 'Family event',
            'status' => 'pending',
            'created_by' => $this->tenantAUser->id,
        ]);

        $response = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/hrm/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('HRM/Dashboard')
            ->where('stats.total_employees', 2)
            ->where('stats.active_employees', 2)
            ->where('stats.present_today', 1)
            ->where('stats.absent_today', 1)
            ->where('stats.pending_leaves', 1)
            ->has('recentEmployees', 2)
            ->has('recentLeaves', 1));
    }

    public function test_crm_dashboard_tracks_pipeline_and_deal_stages(): void
    {
        $pipeline = CrmPipeline::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Enterprise Sales',
            'is_default' => true,
            'created_by' => $this->tenantAUser->id,
        ]);

        $stage1 = DB::table('crm_stages')->insertGetId([
            'pipeline_id' => $pipeline->id,
            'name' => 'Discovery',
            'probability' => 20,
            'position' => 0,
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stage2 = DB::table('crm_stages')->insertGetId([
            'pipeline_id' => $pipeline->id,
            'name' => 'Won',
            'probability' => 100,
            'position' => 1,
            'is_closed' => true,
            'outcome' => 'won',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CrmLead::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage1,
            'name' => 'Lead One',
            'email' => 'lead1@enterprise.local',
            'company' => 'Enterprise Inc',
            'estimated_value' => 25000,
            'status' => 'converted',
            'converted_at' => now(),
            'created_by' => $this->tenantAUser->id,
        ]);

        CrmDeal::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage1,
            'name' => 'Big Deal A',
            'value' => 25000,
            'status' => 'open',
            'created_by' => $this->tenantAUser->id,
        ]);

        CrmDeal::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage2,
            'name' => 'Closed Deal A',
            'value' => 15000,
            'status' => 'won',
            'created_by' => $this->tenantAUser->id,
        ]);

        $response = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/crm/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CRM/Dashboard')
            ->where('stats.total_leads', 1)
            ->where('stats.converted_leads', 1)
            ->where('stats.conversion_rate', 100)
            ->where('stats.total_deals', 2)
            ->where('stats.open_deals', 1)
            ->where('stats.won_deals', 1)
            ->where('stats.pipeline_value', 25000)
            ->where('stats.won_value', 15000)
            ->has('stageDistribution', 2));
    }

    public function test_taskly_dashboard_computes_velocity_and_completion_rates(): void
    {
        $project = TasklyProject::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Platform Launch',
            'status' => 'active',
            'budget' => 50000,
            'created_by' => $this->tenantAUser->id,
        ]);

        $stage = DB::table('taskly_stages')->insertGetId([
            'project_id' => $project->id,
            'name' => 'In Progress',
            'position' => 0,
            'is_complete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TasklyTask::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'project_id' => $project->id,
            'stage_id' => $stage,
            'title' => 'Build Architecture',
            'priority' => 'critical',
            'completed_at' => now(),
            'created_by' => $this->tenantAUser->id,
        ]);

        TasklyTask::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'project_id' => $project->id,
            'stage_id' => $stage,
            'title' => 'Write Docs',
            'priority' => 'medium',
            'due_on' => now()->subDay()->toDateString(),
            'created_by' => $this->tenantAUser->id,
        ]);

        $response = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/taskly/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Taskly/Dashboard')
            ->where('stats.total_projects', 1)
            ->where('stats.active_projects', 1)
            ->where('stats.total_tasks', 2)
            ->where('stats.completed_tasks', 1)
            ->where('stats.open_tasks', 1)
            ->where('stats.overdue_tasks', 1)
            ->where('stats.completion_rate', 50)
            ->where('taskPriority.critical', 1)
            ->where('taskPriority.medium', 1)
            ->has('recentTasks', 2));
    }

    public function test_pos_dashboard_tracks_today_orders_and_tender_breakdown(): void
    {
        $wh = Warehouse::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Store Warehouse',
            'created_by' => $this->tenantAUser->id,
        ]);

        $reg = PosRegister::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'warehouse_id' => $wh->id,
            'name' => 'Main Register',
            'created_by' => $this->tenantAUser->id,
        ]);

        $session = PosSession::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'register_id' => $reg->id,
            'opened_by' => $this->tenantAUser->id,
            'opening_cash' => 100,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        PosOrder::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'session_id' => $session->id,
            'receipt_number' => 'POS-A-001',
            'customer_name' => 'Walk-in Customer',
            'subtotal' => 150,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 150,
            'paid_amount' => 150,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->tenantAUser->id,
        ]);

        PosOrder::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'session_id' => $session->id,
            'receipt_number' => 'POS-A-002',
            'customer_name' => 'Card Customer',
            'subtotal' => 350,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 350,
            'paid_amount' => 350,
            'change_amount' => 0,
            'payment_method' => 'card',
            'status' => 'completed',
            'created_by' => $this->tenantAUser->id,
        ]);

        $response = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/pos/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('POS/Dashboard')
            ->where('stats.today_orders', 2)
            ->where('stats.today_revenue', 500)
            ->where('stats.total_sales', 2)
            ->where('stats.total_revenue', 500)
            ->where('stats.avg_order_value', 250)
            ->where('paymentBreakdown.cash', 150)
            ->where('paymentBreakdown.card', 350)
            ->has('recentOrders', 2));
    }

    public function test_inventory_dashboard_computes_stock_and_warehouse_distribution(): void
    {
        $wh1 = Warehouse::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Central Hub',
            'created_by' => $this->tenantAUser->id,
        ]);

        $item1 = ProductServiceItem::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'name' => 'Super Widget',
            'sku' => 'WIDGET-01',
            'type' => 'product',
            'sale_price' => 100,
            'purchase_price' => 50,
            'created_by' => $this->tenantAUser->id,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $wh1->id,
            'product_id' => $item1->id,
            'quantity' => 120,
        ]);

        $response = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/inventory/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProductService/Dashboard')
            ->where('stats.total_products', 1)
            ->where('stats.total_warehouses', 1)
            ->where('stats.total_stock_units', 120)
            ->has('warehouseDistribution', 1)
            ->where('warehouseDistribution.0.name', 'Central Hub')
            ->where('warehouseDistribution.0.stock_units', 120));
    }

    public function test_sales_and_procurement_dashboards_isolate_receivables_and_payables(): void
    {
        // Sales Invoices & Proposals
        SalesInvoice::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'invoice_id' => 3001,
            'status' => 'posted',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 3000,
            'created_by' => $this->tenantAUser->id,
        ]);

        SalesProposal::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'proposal_id' => 701,
            'status' => 'accepted',
            'issue_date' => now()->toDateString(),
            'total_amount' => 5000,
            'created_by' => $this->tenantAUser->id,
        ]);

        // Purchase Invoices
        PurchaseInvoice::create([
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWs->id,
            'invoice_id' => 4001,
            'status' => 'posted',
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 1500,
            'created_by' => $this->tenantAUser->id,
        ]);

        // Sales Dashboard Test
        $salesRes = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/sales/dashboard');

        $salesRes->assertOk();
        $salesRes->assertInertia(fn (Assert $page) => $page
            ->component('SalesInvoices/Dashboard')
            ->where('stats.total_invoices', 1)
            ->where('stats.posted_invoices', 1)
            ->where('stats.total_sales_amount', 3000)
            ->where('stats.outstanding_receivables', 3000)
            ->where('stats.total_proposals', 1)
            ->where('stats.accepted_proposals', 1)
            ->where('stats.conversion_rate', 100));

        // Procurement Dashboard Test
        $procRes = $this->actingAs($this->tenantAUser)
            ->withSession(['active_organization_id' => $this->tenantAOrg->id, 'active_workspace_id' => $this->tenantAWs->id])
            ->get('/procurement/dashboard');

        $procRes->assertOk();
        $procRes->assertInertia(fn (Assert $page) => $page
            ->component('PurchaseInvoices/Dashboard')
            ->where('stats.total_purchase_invoices', 1)
            ->where('stats.posted_purchase_invoices', 1)
            ->where('stats.total_purchasing_amount', 1500)
            ->where('stats.outstanding_payables', 1500));
    }
}
