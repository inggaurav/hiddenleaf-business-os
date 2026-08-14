<?php

namespace Tests\Feature\Security;

use App\Models\AccountCreditNote;
use App\Models\AccountCustomer;
use App\Models\AccountDebitNote;
use App\Models\AccountExpense;
use App\Models\AccountRevenue;
use App\Models\AccountType;
use App\Models\AccountVendor;
use App\Models\CustomerPayment;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\VendorPayment;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setupTenantEnv(string $prefix): array
    {
        $user = User::factory()->create(['role' => 'company_admin', 'name' => "User {$prefix}", 'email' => "user{$prefix}@test.com"]);
        $plan = Plan::create(['name' => "Plan {$prefix}", 'status' => true, 'modules' => ['account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id, 'name' => "Org {$prefix}"]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id, 'name' => "WS {$prefix}"]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        UserActiveModule::create(['workspace_id' => $ws->id, 'module_name' => 'account']);

        $assetType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Current Assets', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $incomeType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Operating Income', 'classification' => 'income', 'normal_balance' => 'credit']);
        $expenseType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Operating Expense', 'classification' => 'expense', 'normal_balance' => 'debit']);

        $bankAccount = LedgerAccount::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'account_type_id' => $assetType->id, 'code' => "1010-{$prefix}", 'name' => "Bank {$prefix}", 'currency' => 'USD', 'is_bank' => true, 'is_active' => true]);

        $customer = AccountCustomer::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => "Customer {$prefix}", 'email' => "c{$prefix}@test.com", 'balance' => 1000]);
        $vendor = AccountVendor::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => "Vendor {$prefix}", 'email' => "v{$prefix}@test.com", 'balance' => 1000]);

        $salesInvoice = SalesInvoice::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'invoice_id' => "SI-{$prefix}-1", 'customer_id' => $customer->id, 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'total_amount' => 1000, 'status' => 'posted', 'created_by' => $user->id]);
        $purchaseInvoice = PurchaseInvoice::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'invoice_id' => "PI-{$prefix}-1", 'vendor_id' => $vendor->id, 'purchase_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'total_amount' => 1000, 'status' => 'posted', 'created_by' => $user->id]);

        return compact('user', 'org', 'ws', 'bankAccount', 'customer', 'vendor', 'salesInvoice', 'purchaseInvoice');
    }

    public function test_customer_payment_with_foreign_customer_id_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/customer-payments', [
                'customer_id' => $envB['customer']->id,
                'invoice_id' => $envA['salesInvoice']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])->assertStatus(404);
    }

    public function test_customer_payment_with_foreign_invoice_id_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/customer-payments', [
                'customer_id' => $envA['customer']->id,
                'invoice_id' => $envB['salesInvoice']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])->assertStatus(404);
    }

    public function test_customer_payment_with_mismatched_customer_and_invoice_returns_422(): void
    {
        $envA = $this->setupTenantEnv('A');
        $customerA2 = AccountCustomer::create(['organization_id' => $envA['org']->id, 'workspace_id' => $envA['ws']->id, 'name' => 'Customer A2', 'email' => 'ca2@test.com', 'balance' => 0]);

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/customer-payments', [
                'customer_id' => $customerA2->id,
                'invoice_id' => $envA['salesInvoice']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])->assertStatus(422);
    }

    public function test_vendor_payment_with_foreign_vendor_id_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/vendor-payments', [
                'vendor_id' => $envB['vendor']->id,
                'purchase_invoice_id' => $envA['purchaseInvoice']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])->assertStatus(404);
    }

    public function test_vendor_payment_with_foreign_purchase_invoice_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/vendor-payments', [
                'vendor_id' => $envA['vendor']->id,
                'purchase_invoice_id' => $envB['purchaseInvoice']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])->assertStatus(404);
    }

    public function test_revenue_with_foreign_customer_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/revenues', [
                'customer_id' => $envB['customer']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 100,
                'date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])->assertStatus(404);
    }

    public function test_expense_with_foreign_vendor_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/expenses', [
                'vendor_id' => $envB['vendor']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 100,
                'date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])->assertStatus(404);
    }

    public function test_credit_note_with_foreign_invoice_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/credit-notes', [
                'invoice_id' => $envB['salesInvoice']->id,
                'amount' => 100,
                'date' => now()->toDateString(),
            ])->assertStatus(404);
    }

    public function test_debit_note_with_foreign_purchase_invoice_returns_404(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/debit-notes', [
                'purchase_invoice_id' => $envB['purchaseInvoice']->id,
                'amount' => 100,
                'date' => now()->toDateString(),
            ])->assertStatus(404);
    }

    public function test_foreign_balance_unchanged_after_attack_attempts(): void
    {
        $envA = $this->setupTenantEnv('A');
        $envB = $this->setupTenantEnv('B');

        $initialCustomerBalance = $envB['customer']->balance;
        $initialVendorBalance = $envB['vendor']->balance;

        // Attempt attack: Pay Tenant B customer from Tenant A context
        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/customer-payments', [
                'customer_id' => $envB['customer']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ]);

        // Attempt attack: Pay Tenant B vendor from Tenant A context
        $this->actingAs($envA['user'])
            ->withSession(['active_organization_id' => $envA['org']->id, 'active_workspace_id' => $envA['ws']->id])
            ->postJson('/accounting/vendor-payments', [
                'vendor_id' => $envB['vendor']->id,
                'account_id' => $envA['bankAccount']->id,
                'amount' => 500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ]);

        $this->assertEquals($initialCustomerBalance, $envB['customer']->fresh()->balance);
        $this->assertEquals($initialVendorBalance, $envB['vendor']->fresh()->balance);
    }
}
