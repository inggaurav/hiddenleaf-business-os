<?php

namespace Tests\Feature\Account;

use App\Models\AccountCustomer;
use App\Models\AccountType;
use App\Models\CustomerPayment;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountReferenceParityTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): array
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Account Reference', 'status' => true, 'modules' => ['account', 'sales', 'procurement', 'productservice'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);
        foreach (['account', 'sales', 'procurement', 'productservice'] as $module) {
            UserActiveModule::firstOrCreate(['workspace_id' => $workspace->id, 'module_name' => $module]);
        }

        $asset = AccountType::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Assets'],
            ['organization_id' => $org->id, 'classification' => 'asset', 'normal_balance' => 'debit']
        );
        $bank = LedgerAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'account_type_id' => $asset->id,
            'code' => '1010',
            'name' => 'Operating Bank',
            'currency' => 'USD',
            'is_bank' => true,
            'is_active' => true,
        ]);

        return compact('user', 'org', 'workspace', 'asset', 'bank');
    }

    private function asTenant(array $tenant): self
    {
        return $this->actingAs($tenant['user'])->withSession([
            'active_organization_id' => $tenant['org']->id,
            'active_workspace_id' => $tenant['workspace']->id,
        ]);
    }

    public function test_bank_account_and_category_reference_workflows_are_real(): void
    {
        $t = $this->tenant();

        $this->asTenant($t)->post('/accounting/bank-accounts', [
            'account_type_id' => $t['asset']->id,
            'code' => '1020',
            'name' => 'Reserve Bank',
            'currency' => 'USD',
            'bank_name' => 'Hidden Bank',
            'account_holder' => 'HiddenLeaf LLC',
            'account_number' => '998877',
            'opening_balance' => '250.00',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('ledger_accounts', ['workspace_id' => $t['workspace']->id, 'code' => '1020', 'bank_name' => 'Hidden Bank']);
        $this->assertDatabaseHas('journal_entries', ['workspace_id' => $t['workspace']->id, 'reference' => 'BANK-OPEN-'.LedgerAccount::where('code', '1020')->value('id'), 'status' => 'posted']);

        $this->asTenant($t)->post('/accounting/revenue-categories', ['name' => 'Consulting', 'description' => 'Consulting revenue', 'is_active' => true])->assertRedirect();
        $this->asTenant($t)->post('/accounting/expense-categories', ['name' => 'Hosting', 'description' => 'Infrastructure', 'is_active' => true])->assertRedirect();

        $this->assertDatabaseHas('account_transaction_categories', ['workspace_id' => $t['workspace']->id, 'type' => 'revenue', 'name' => 'Consulting']);
        $this->assertDatabaseHas('account_transaction_categories', ['workspace_id' => $t['workspace']->id, 'type' => 'expense', 'name' => 'Hosting']);
    }

    public function test_invoice_posting_updates_inventory_and_general_ledger_atomically(): void
    {
        $t = $this->tenant();
        $customer = AccountCustomer::create(['organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'name' => 'Reference Customer']);
        $warehouse = Warehouse::create(['organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'name' => 'Main', 'created_by' => $t['user']->id]);
        $product = ProductServiceItem::create([
            'organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'name' => 'Switch', 'sku' => 'SW-REF', 'type' => 'product',
            'sale_price' => 500, 'purchase_price' => 300, 'is_active' => true, 'created_by' => $t['user']->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 10]);
        $invoice = SalesInvoice::create([
            'organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'warehouse_id' => $warehouse->id,
            'invoice_id' => 'SI-REF-1', 'customer_id' => $customer->id, 'issue_date' => now()->toDateString(), 'total_amount' => 1000,
            'status' => 0, 'created_by' => $t['user']->id,
        ]);
        SalesInvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 500]);

        $this->asTenant($t)->post("/sales-invoices/{$invoice->id}/post")->assertRedirect();

        $this->assertEquals(8, (int) WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseHas('journal_entries', ['workspace_id' => $t['workspace']->id, 'reference' => 'SALE-SI-REF-1', 'status' => 'posted']);
        $this->assertEquals('1000.00', $customer->fresh()->balance);
    }

    public function test_customer_payment_void_reverses_ledger_and_restores_outstanding(): void
    {
        $t = $this->tenant();
        $customer = AccountCustomer::create(['organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'name' => 'Payment Customer']);
        $invoice = SalesInvoice::create([
            'organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'invoice_id' => 'SI-PAY-REF', 'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(), 'total_amount' => 1000, 'status' => 'posted', 'created_by' => $t['user']->id,
        ]);

        $this->asTenant($t)->post('/accounting/customer-payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'account_id' => $t['bank']->id,
            'amount' => '400.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'idempotency_key' => 'reference-void-payment',
        ])->assertRedirect();

        $payment = CustomerPayment::includingVoided()->where('idempotency_key', 'reference-void-payment')->firstOrFail();
        $this->assertEquals('partial', (string) $invoice->fresh()->status);

        $this->asTenant($t)->delete("/accounting/customer-payments/{$payment->id}")->assertRedirect();

        $this->assertDatabaseHas('customer_payments', ['id' => $payment->id, 'status' => 'void']);
        $this->assertEquals('posted', (string) $invoice->fresh()->status);
        $this->assertEquals('1000.00', $customer->fresh()->balance);
        $this->assertDatabaseHas('journal_entries', ['workspace_id' => $t['workspace']->id, 'reference' => 'VOID-CUST-PAY-'.$payment->id, 'status' => 'posted']);
    }

    public function test_invoice_aging_and_customer_balance_reports_use_true_outstanding(): void
    {
        $t = $this->tenant();
        $customer = AccountCustomer::create(['organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'name' => 'Aging Customer']);
        $invoice = SalesInvoice::create([
            'organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'invoice_id' => 'SI-AGING', 'customer_id' => $customer->id,
            'issue_date' => now()->subDays(60)->toDateString(), 'due_date' => now()->subDays(30)->toDateString(), 'total_amount' => 1000,
            'status' => 'partial', 'created_by' => $t['user']->id,
        ]);
        CustomerPayment::create([
            'organization_id' => $t['org']->id, 'workspace_id' => $t['workspace']->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id,
            'amount' => 250, 'payment_date' => now()->subDays(20)->toDateString(), 'payment_method' => 'cash', 'status' => 'posted',
        ]);

        $aging = $this->asTenant($t)->getJson('/accounting/reports/invoice-aging')->assertOk()->json();
        $this->assertSame(750.0, (float) $aging['grand_total']);

        $balance = $this->asTenant($t)->getJson('/accounting/reports/customer-balance?show_zero_balances=1')->assertOk()->json();
        $this->assertSame(750.0, (float) $balance['total_balance']);
    }

    public function test_cross_tenant_reference_routes_do_not_expose_bank_accounts(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $foreign = LedgerAccount::create([
            'organization_id' => $b['org']->id, 'workspace_id' => $b['workspace']->id, 'account_type_id' => $b['asset']->id,
            'code' => '1099', 'name' => 'Foreign Bank', 'currency' => 'USD', 'is_bank' => true, 'is_active' => true,
        ]);

        $this->asTenant($a)->get("/accounting/bank-accounts/{$foreign->id}/edit")->assertNotFound();
        $this->asTenant($a)->delete("/accounting/bank-accounts/{$foreign->id}")->assertNotFound();
    }
}
