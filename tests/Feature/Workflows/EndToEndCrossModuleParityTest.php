<?php

namespace Tests\Feature\Workflows;

use App\Models\AccountCustomer;
use App\Models\AccountVendor;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrPayslip;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\PosSession;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturn;
use App\Models\SalesProposal;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndToEndCrossModuleParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setupFullSuiteTenant(): array
    {
        $this->seed();

        $modules = ['account', 'hrm', 'lead', 'taskly', 'pos', 'productservice', 'sales', 'procurement'];

        $user = User::factory()->create(['name' => 'Enterprise Operator', 'role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Unlimited Enterprise', 'status' => true, 'modules' => $modules, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        foreach ($modules as $mod) {
            UserActiveModule::create(['workspace_id' => $ws->id, 'module_name' => $mod]);
        }

        return compact('user', 'org', 'ws', 'plan');
    }

    public function test_commercial_to_financial_inventory_closed_loop(): void
    {
        $tenant = $this->setupFullSuiteTenant();
        $session = [
            'active_organization_id' => $tenant['org']->id,
            'active_workspace_id' => $tenant['ws']->id,
        ];

        // 1. Create Warehouse
        $warehouse = Warehouse::create([
            'organization_id' => $tenant['org']->id,
            'workspace_id' => $tenant['ws']->id,
            'name' => 'Central Hub Warehouse',
            'created_by' => $tenant['user']->id,
        ]);

        // 2. Create Vendor
        $vendor = AccountVendor::create([
            'organization_id' => $tenant['org']->id,
            'workspace_id' => $tenant['ws']->id,
            'name' => 'Global Silicon Hardware',
            'email' => 'sales@globalsilicon.com',
            'created_by' => $tenant['user']->id,
        ]);

        // 3. Create Customer
        $customer = AccountCustomer::create([
            'organization_id' => $tenant['org']->id,
            'workspace_id' => $tenant['ws']->id,
            'name' => 'Enterprise Telecom Ltd',
            'email' => 'procurement@telecom.com',
            'created_by' => $tenant['user']->id,
        ]);

        // 4. Create Product
        $product = ProductServiceItem::create([
            'organization_id' => $tenant['org']->id,
            'workspace_id' => $tenant['ws']->id,
            'name' => 'Enterprise Fiber Switch 24P',
            'sku' => 'SW-24P-PRO',
            'type' => 'product',
            'sale_price' => 1200.00,
            'purchase_price' => 700.00,
            'is_active' => true,
            'created_by' => $tenant['user']->id,
        ]);

        // 5. Create Purchase Invoice (Vendor Bill) and Post
        $this->actingAs($tenant['user'])->withSession($session)->post('/purchase-invoices', [
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20, 'price' => 700.00],
            ],
        ])->assertSessionHasNoErrors();

        $purchase = PurchaseInvoice::where('organization_id', $tenant['org']->id)->first();
        $this->actingAs($tenant['user'])->withSession($session)->post("/purchase-invoices/{$purchase->id}/post");

        // Verify stock is now 20
        $stock = WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->first();
        $this->assertEquals(20, (int) $stock->quantity);

        // 6. Create Sales Proposal
        $this->actingAs($tenant['user'])->withSession($session)->post('/sales-proposals', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 8, 'price' => 1200.00],
            ],
        ])->assertSessionHasNoErrors();

        $proposal = SalesProposal::where('organization_id', $tenant['org']->id)->first();
        $this->actingAs($tenant['user'])->withSession($session)->post("/sales-proposals/{$proposal->id}/sent")->assertRedirect();
        $this->actingAs($tenant['user'])->withSession($session)->post("/sales-proposals/{$proposal->id}/accept")->assertRedirect();
        $convertResp = $this->actingAs($tenant['user'])->withSession($session)->post("/sales-proposals/{$proposal->id}/convert-to-invoice");
        $convertResp->assertRedirect();

        // 7. Post Sales Invoice
        $sale = SalesInvoice::where('organization_id', $tenant['org']->id)->first();
        $this->assertNotNull($sale);
        $this->actingAs($tenant['user'])->withSession($session)->post("/sales-invoices/{$sale->id}/post")->assertSessionHasNoErrors();

        // Verify stock decremented to 12
        $this->assertEquals(12, (int) $stock->fresh()->quantity);

        // 8. Customer Payments (Partial & Full)
        $this->actingAs($tenant['user'])->withSession($session)->post('/accounting/customer-payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $sale->id,
            'amount' => 4600.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'WIRE-PART-1',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('partial', $sale->fresh()->status);

        $this->actingAs($tenant['user'])->withSession($session)->post('/accounting/customer-payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $sale->id,
            'amount' => 5000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'WIRE-FINAL-2',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('paid', $sale->fresh()->status);

        // 9. Sales Return (Return 2 units)
        $this->actingAs($tenant['user'])->withSession($session)->post('/sales-returns', [
            'customer_id' => $customer->id,
            'sales_invoice_id' => $sale->id,
            'date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 1200.00],
            ],
        ])->assertSessionHasNoErrors();

        $return = SalesInvoiceReturn::sole();
        $this->actingAs($tenant['user'])->withSession($session)->post("/sales-returns/{$return->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($tenant['user'])->withSession($session)->post("/sales-returns/{$return->id}/complete")->assertSessionHasNoErrors();

        // Stock restored from 12 to 14
        $this->assertEquals(14, (int) $stock->fresh()->quantity);

        // 10. Issue Credit Note
        $this->actingAs($tenant['user'])->withSession($session)->post('/accounting/credit-notes', [
            'customer_id' => $customer->id,
            'invoice_id' => $sale->id,
            'amount' => 2400.00,
            'date' => now()->toDateString(),
            'description' => 'Return adjustment credit note for 2 returned switches',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('account_credit_notes', [
            'workspace_id' => $tenant['ws']->id,
            'customer_id' => $customer->id,
            'amount' => 2400.00,
        ]);
    }

    public function test_hrm_to_project_to_payroll_closed_loop(): void
    {
        $tenant = $this->setupFullSuiteTenant();
        $session = [
            'active_organization_id' => $tenant['org']->id,
            'active_workspace_id' => $tenant['ws']->id,
        ];

        // 1. Create Employee
        $this->actingAs($tenant['user'])->withSession($session)->post('/hrm/employees', [
            'employee_number' => 'EMP-1008',
            'name' => 'Sophia Engineer',
            'email' => 'sophia@acme.local',
            'joined_at' => now()->subMonths(6)->toDateString(),
            'basic_salary' => 8000.00,
        ])->assertSessionHasNoErrors();

        $employee = HrEmployee::where('employee_number', 'EMP-1008')->first();
        $this->assertNotNull($employee);

        // 2. Clock in & out attendance
        $this->actingAs($tenant['user'])->withSession($session)->post('/hrm/attendance', [
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'notes' => 'On-site system development',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hr_attendance', [
            'employee_id' => $employee->id,
            'status' => 'present',
        ]);
        $this->assertEquals(now()->toDateString(), Carbon::parse(HrAttendance::first()->attendance_date)->toDateString());

        // 3. Project & Task Tracking
        $this->actingAs($tenant['user'])->withSession($session)->post('/taskly/projects', [
            'name' => 'Core Architecture Refactor',
            'status' => 'active',
            'stages' => [
                ['name' => 'To Do'],
                ['name' => 'In Progress'],
                ['name' => 'Done', 'is_complete' => true],
            ],
        ])->assertSessionHasNoErrors();

        $project = TasklyProject::where('organization_id', $tenant['org']->id)->first();
        $stageId = DB::table('taskly_stages')->where('project_id', $project->id)->value('id') ?? 1;

        $this->actingAs($tenant['user'])->withSession($session)->post('/taskly/tasks', [
            'project_id' => $project->id,
            'stage_id' => $stageId,
            'title' => 'Implement Double-Entry Ledger Service',
            'priority' => 'critical',
        ])->assertSessionHasNoErrors();

        $task = TasklyTask::where('project_id', $project->id)->first();

        // 4. Log timesheet
        $this->actingAs($tenant['user'])->withSession($session)->post('/taskly/timesheets', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'user_id' => $tenant['user']->id,
            'work_date' => now()->toDateString(),
            'hours' => 7.5,
            'description' => 'Engineered ledger posting routines',
        ])->assertSessionHasNoErrors();

        // 5. Generate Monthly Payroll
        $this->actingAs($tenant['user'])->withSession($session)->post('/hrm/payslips', [
            'employee_id' => $employee->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $payslip = HrPayslip::where('employee_id', $employee->id)->first();
        $this->assertNotNull($payslip);
        $this->assertEquals(8000.00, (float) $payslip->gross_pay);
        $this->assertEquals(8000.00, (float) $payslip->net_pay);
    }

    public function test_pos_inventory_protection_and_payment_overrun_protection(): void
    {
        $tenant = $this->setupFullSuiteTenant();
        $session = [
            'active_organization_id' => $tenant['org']->id,
            'active_workspace_id' => $tenant['ws']->id,
        ];

        $warehouse = Warehouse::create([
            'organization_id' => $tenant['org']->id,
            'workspace_id' => $tenant['ws']->id,
            'name' => 'POS Retail Outlet',
            'created_by' => $tenant['user']->id,
        ]);

        $product = ProductServiceItem::create([
            'organization_id' => $tenant['org']->id,
            'workspace_id' => $tenant['ws']->id,
            'name' => 'High-Speed USB Dongle',
            'sku' => 'DONGLE-4K',
            'barcode' => '890999001',
            'type' => 'product',
            'sale_price' => 50.00,
            'purchase_price' => 20.00,
            'is_active' => true,
            'created_by' => $tenant['user']->id,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Open Register and Session
        $this->actingAs($tenant['user'])->withSession($session)->post('/pos/registers', [
            'warehouse_id' => $warehouse->id,
            'name' => 'Terminal 1',
        ])->assertSessionHasNoErrors();

        $register = PosRegister::sole();
        $this->actingAs($tenant['user'])->withSession($session)->post("/pos/registers/{$register->id}/open", [
            'opening_cash' => 500.00,
        ])->assertSessionHasNoErrors();

        $posSession = PosSession::sole();

        // 1. Oversell attempt (Attempting to purchase 10 when only 5 exist)
        $this->actingAs($tenant['user'])->withSession($session)->post("/pos/sessions/{$posSession->id}/checkout", [
            'payment_method' => 'cash',
            'paid_amount' => 500.00,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'tax_percent' => 0, 'discount_percent' => 0],
            ],
        ])->assertServerError();

        // Verify stock remains untouched at 5
        $this->assertEquals(5, (int) WarehouseStock::where('product_id', $product->id)->value('quantity'));

        // 2. Valid checkout (Purchase 3 units)
        $this->actingAs($tenant['user'])->withSession($session)->post("/pos/sessions/{$posSession->id}/checkout", [
            'payment_method' => 'cash',
            'paid_amount' => 150.00,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'tax_percent' => 0, 'discount_percent' => 0],
            ],
        ])->assertSessionHasNoErrors();

        // Verify stock decremented to 2
        $this->assertEquals(2, (int) WarehouseStock::where('product_id', $product->id)->value('quantity'));

        // 3. Customer invoice overpayment rejection
        $customer = AccountCustomer::create([
            'organization_id' => $tenant['org']->id,
            'workspace_id' => $tenant['ws']->id,
            'name' => 'Client Bravo',
            'created_by' => $tenant['user']->id,
        ]);

        $this->actingAs($tenant['user'])->withSession($session)->post('/sales-invoices', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 50.00],
            ],
        ])->assertSessionHasNoErrors();

        $invoice = SalesInvoice::where('customer_id', $customer->id)->first();
        $this->actingAs($tenant['user'])->withSession($session)->post("/sales-invoices/{$invoice->id}/post");

        // Attempting to record $200 payment for a $50 invoice
        $this->actingAs($tenant['user'])->withSession($session)->post('/accounting/customer-payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 200.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertUnprocessable();
    }
}
