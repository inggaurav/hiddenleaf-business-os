<?php

namespace Tests\Feature\Account;

use App\Models\AccountCustomer;
use App\Models\AccountType;
use App\Models\AccountVendor;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountFullWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setupWorkspaceEnvironment(): array
    {
        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Enterprise', 'status' => true, 'modules' => ['account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        UserActiveModule::create(['workspace_id' => $ws->id, 'module_name' => 'account']);

        // Account Types
        $assetType = AccountType::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Current Assets',
            'classification' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $incomeType = AccountType::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Operating Income',
            'classification' => 'income',
            'normal_balance' => 'credit',
        ]);

        $expenseType = AccountType::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Operating Expense',
            'classification' => 'expense',
            'normal_balance' => 'debit',
        ]);

        // Bank Account & AR Account
        $bankAccount = LedgerAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_type_id' => $assetType->id,
            'code' => '1010',
            'name' => 'Main Operating Bank Account',
            'currency' => 'USD',
            'is_bank' => true,
            'is_active' => true,
        ]);

        $arAccount = LedgerAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_type_id' => $assetType->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'currency' => 'USD',
            'is_bank' => false,
            'is_active' => true,
        ]);

        $incomeAccount = LedgerAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_type_id' => $incomeType->id,
            'code' => '4000',
            'name' => 'Sales Revenue Account',
            'currency' => 'USD',
            'is_bank' => false,
            'is_active' => true,
        ]);

        $expenseAccount = LedgerAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_type_id' => $expenseType->id,
            'code' => '5000',
            'name' => 'General Expense Account',
            'currency' => 'USD',
            'is_bank' => false,
            'is_active' => true,
        ]);

        return compact('user', 'org', 'ws', 'assetType', 'incomeType', 'expenseType', 'bankAccount', 'arAccount', 'incomeAccount', 'expenseAccount');
    }

    public function test_customer_and_vendor_full_lifecycle(): void
    {
        $env = $this->setupWorkspaceEnvironment();

        // 1. Create Customer
        $customerData = [
            'name' => 'Acme Global Corp',
            'email' => 'billing@acmeglobal.com',
            'contact' => '+1 555 0192',
            'tax_number' => 'VAT-992019',
            'billing_city' => 'San Francisco',
            'billing_country' => 'USA',
        ];

        $resp = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customers', $customerData);

        $resp->assertRedirect();
        $this->assertDatabaseHas('account_customers', [
            'workspace_id' => $env['ws']->id,
            'name' => 'Acme Global Corp',
            'email' => 'billing@acmeglobal.com',
        ]);

        // 2. Create Vendor
        $vendorData = [
            'name' => 'Apex Cloud Logistics',
            'email' => 'orders@apexlogistics.com',
            'contact' => '+1 555 7711',
            'tax_number' => 'TAX-77291',
            'billing_city' => 'Austin',
            'billing_country' => 'USA',
        ];

        $resp = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/vendors', $vendorData);

        $resp->assertRedirect();
        $this->assertDatabaseHas('account_vendors', [
            'workspace_id' => $env['ws']->id,
            'name' => 'Apex Cloud Logistics',
        ]);

        // 3. View Customers & Vendors index
        $viewCust = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->get('/accounting/customers');
        $viewCust->assertOk();
        $viewCust->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Customers/Index')
            ->has('customers.data', 1)
        );

        $viewVend = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->get('/accounting/vendors');
        $viewVend->assertOk();
        $viewVend->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Vendors/Index')
            ->has('vendors.data', 1)
        );
    }

    public function test_customer_payment_records_and_updates_invoice_status(): void
    {
        $env = $this->setupWorkspaceEnvironment();

        $customer = AccountCustomer::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'name' => 'Customer Omega',
            'email' => 'omega@test.com',
            'balance' => 1000.00,
        ]);

        $invoice = SalesInvoice::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'invoice_id' => 3001,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 1000.00,
            'status' => 'posted',
            'created_by' => $env['user']->id,
        ]);

        // Partial payment of 400
        $resp = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', [
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'account_id' => $env['bankAccount']->id,
                'amount' => 400.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'reference' => 'TXN-PARTIAL-1',
            ]);

        $resp->assertRedirect();
        $this->assertEquals('partial', $invoice->fresh()->status);
        $this->assertDatabaseHas('customer_payments', [
            'workspace_id' => $env['ws']->id,
            'invoice_id' => $invoice->id,
            'amount' => 400.00,
        ]);

        // Remainder payment of 600
        $resp2 = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', [
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'account_id' => $env['bankAccount']->id,
                'amount' => 600.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'reference' => 'TXN-FINAL-2',
            ]);

        $resp2->assertRedirect();
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(0.00, (float) $customer->fresh()->balance);
    }

    public function test_vendor_payment_records_and_updates_purchase_bill_status(): void
    {
        $env = $this->setupWorkspaceEnvironment();

        $vendor = AccountVendor::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'name' => 'Supplier Delta',
            'email' => 'delta@test.com',
            'balance' => 850.00,
        ]);

        $bill = PurchaseInvoice::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'invoice_id' => 4001,
            'vendor_id' => $vendor->id,
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 850.00,
            'status' => 'posted',
            'created_by' => $env['user']->id,
        ]);

        // Full Payment
        $resp = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/vendor-payments', [
                'vendor_id' => $vendor->id,
                'purchase_invoice_id' => $bill->id,
                'account_id' => $env['bankAccount']->id,
                'amount' => 850.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'reference' => 'BILL-DISBURSE-1',
            ]);

        $resp->assertRedirect();
        $this->assertEquals('paid', $bill->fresh()->status);
        $this->assertEquals(0.00, (float) $vendor->fresh()->balance);
    }

    public function test_direct_revenues_and_expenses_with_credit_debit_notes(): void
    {
        $env = $this->setupWorkspaceEnvironment();

        // 1. Direct Revenue
        $respRev = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/revenues', [
                'account_id' => $env['bankAccount']->id,
                'amount' => 1500.00,
                'date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'reference' => 'Advisory Retainer',
            ]);
        $respRev->assertRedirect();
        $this->assertDatabaseHas('account_revenues', [
            'workspace_id' => $env['ws']->id,
            'amount' => 1500.00,
        ]);

        // 2. Direct Expense
        $respExp = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/expenses', [
                'account_id' => $env['bankAccount']->id,
                'amount' => 350.00,
                'date' => now()->toDateString(),
                'payment_method' => 'credit_card',
                'reference' => 'Cloud Servers',
            ]);
        $respExp->assertRedirect();
        $this->assertDatabaseHas('account_expenses', [
            'workspace_id' => $env['ws']->id,
            'amount' => 350.00,
        ]);

        // 3. Credit Note
        $respCn = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/credit-notes', [
                'amount' => 200.00,
                'date' => now()->toDateString(),
                'description' => 'Promotional credit adjustment',
            ]);
        $respCn->assertRedirect();
        $this->assertDatabaseHas('account_credit_notes', [
            'workspace_id' => $env['ws']->id,
            'amount' => 200.00,
        ]);

        // 4. Debit Note
        $respDn = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/debit-notes', [
                'amount' => 120.00,
                'date' => now()->toDateString(),
                'description' => 'Damaged goods return debit note',
            ]);
        $respDn->assertRedirect();
        $this->assertDatabaseHas('account_debit_notes', [
            'workspace_id' => $env['ws']->id,
            'amount' => 120.00,
        ]);

        // 5. Account Dashboard View
        $dashResp = $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->get('/accounting/dashboard');
        $dashResp->assertOk();
        $dashResp->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Dashboard')
            ->where('stats.total_revenue', 1500)
            ->where('stats.total_expense', 350)
            ->where('stats.net_profit', 1150)
        );
    }
}
