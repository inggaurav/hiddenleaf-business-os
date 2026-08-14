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
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setupEnv(): array
    {
        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Plan Idemp', 'status' => true, 'modules' => ['account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        UserActiveModule::create(['workspace_id' => $ws->id, 'module_name' => 'account']);

        $assetType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Assets', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $bank = LedgerAccount::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'account_type_id' => $assetType->id, 'code' => '1010', 'name' => 'Bank', 'currency' => 'USD', 'is_bank' => true, 'is_active' => true]);

        $customer = AccountCustomer::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Cust', 'balance' => 1000]);
        $vendor = AccountVendor::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Vend', 'balance' => 1000]);

        $sale = SalesInvoice::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'invoice_id' => 'SI-IDEM-1', 'customer_id' => $customer->id, 'issue_date' => now()->toDateString(), 'total_amount' => 1000, 'status' => 'posted', 'created_by' => $user->id]);
        $purchase = PurchaseInvoice::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'invoice_id' => 'PI-IDEM-1', 'vendor_id' => $vendor->id, 'purchase_date' => now()->toDateString(), 'total_amount' => 1000, 'status' => 'posted', 'created_by' => $user->id]);

        return compact('user', 'org', 'ws', 'customer', 'vendor', 'bank', 'sale', 'purchase');
    }

    public function test_same_idempotency_key_with_same_payload_returns_idempotent_success(): void
    {
        $env = $this->setupEnv();

        $payload = [
            'customer_id' => $env['customer']->id,
            'invoice_id' => $env['sale']->id,
            'account_id' => $env['bank']->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'idempotency_key' => 'idemp-cust-same',
        ];

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', $payload)
            ->assertRedirect();

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_same_idempotency_key_with_different_amount_returns_409(): void
    {
        $env = $this->setupEnv();

        $payload = [
            'customer_id' => $env['customer']->id,
            'invoice_id' => $env['sale']->id,
            'account_id' => $env['bank']->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'idempotency_key' => 'idemp-cust-diff',
        ];

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', $payload)
            ->assertRedirect();

        $payload['amount'] = 200;
        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', $payload)
            ->assertStatus(409);
    }

    public function test_same_key_same_amount_but_different_customer_payload_returns_409(): void
    {
        $env = $this->setupEnv();
        $otherCustomer = AccountCustomer::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'name' => 'Other Customer',
            'balance' => 0,
        ]);

        $payload = [
            'customer_id' => $env['customer']->id,
            'account_id' => $env['bank']->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'idempotency_key' => 'same-amount-different-customer',
        ];

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', $payload)
            ->assertRedirect();

        $payload['customer_id'] = $otherCustomer->id;
        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/customer-payments', $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('customer_payments', 1);
    }

    public function test_vendor_payment_idempotency_requires_exact_fingerprint(): void
    {
        $env = $this->setupEnv();

        $payload = [
            'vendor_id' => $env['vendor']->id,
            'purchase_invoice_id' => $env['purchase']->id,
            'account_id' => $env['bank']->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'idempotency_key' => 'idemp-vend-test',
        ];

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/vendor-payments', $payload)
            ->assertRedirect();

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/vendor-payments', $payload)
            ->assertRedirect();

        $payload['payment_method'] = 'bank_transfer';
        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post('/accounting/vendor-payments', $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('vendor_payments', 1);
    }

    public function test_json_financial_submission_without_idempotency_key_is_rejected(): void
    {
        $env = $this->setupEnv();

        $payload = [
            'customer_id' => $env['customer']->id,
            'invoice_id' => $env['sale']->id,
            'account_id' => $env['bank']->id,
            'amount' => 50,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ];

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->postJson('/accounting/customer-payments', $payload)
            ->assertStatus(422);

        $this->assertDatabaseCount('customer_payments', 0);
    }
}
