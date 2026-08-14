<?php

namespace Tests\Feature\Account;

use App\Domain\Accounting\FinancialBalanceService;
use App\Models\AccountCreditNote;
use App\Models\AccountCustomer;
use App\Models\AccountType;
use App\Models\AccountVendor;
use App\Models\CustomerPayment;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\VendorPayment;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setupTenant(): array
    {
        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Full Enterprise', 'status' => true, 'modules' => ['account', 'sales', 'procurement', 'productservice'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        foreach (['account', 'sales', 'procurement', 'productservice'] as $mod) {
            UserActiveModule::create(['workspace_id' => $ws->id, 'module_name' => $mod]);
        }

        $assetType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Bank Assets', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $bank = LedgerAccount::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'account_type_id' => $assetType->id, 'code' => '1010', 'name' => 'Main Bank', 'currency' => 'USD', 'is_bank' => true, 'is_active' => true]);

        $warehouse = Warehouse::create(['name' => 'Primary WH', 'organization_id' => $org->id, 'workspace_id' => $ws->id, 'created_by' => $user->id]);
        $product = ProductServiceItem::create(['name' => 'Server', 'sku' => 'SRV-1', 'type' => 'product', 'sale_price' => 1000, 'purchase_price' => 600, 'organization_id' => $org->id, 'workspace_id' => $ws->id, 'created_by' => $user->id]);
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 50]);

        $customer = AccountCustomer::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Enterprise Client', 'email' => 'client@ent.test', 'balance' => 0]);
        $vendor = AccountVendor::create(['organization_id' => $org->id, 'workspace_id' => $ws->id, 'name' => 'Hardware Vendor', 'email' => 'vendor@hw.test', 'balance' => 0]);

        return compact('user', 'org', 'ws', 'bank', 'warehouse', 'product', 'customer', 'vendor');
    }

    public function test_posted_sales_invoice_cannot_be_hard_deleted(): void
    {
        $env = $this->setupTenant();

        $invoice = SalesInvoice::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'warehouse_id' => $env['warehouse']->id,
            'invoice_id' => 'SI-IMMUT-1',
            'customer_id' => $env['customer']->id,
            'issue_date' => now()->toDateString(),
            'total_amount' => 1000,
            'status' => 0,
            'created_by' => $env['user']->id,
        ]);
        SalesInvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $env['product']->id, 'quantity' => 1, 'price' => 1000]);

        // Post the invoice
        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post("/sales-invoices/{$invoice->id}/post")
            ->assertRedirect();

        $this->assertEquals(1, $invoice->fresh()->status);

        // Attempting to delete the posted invoice must fail with 422
        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->delete("/sales-invoices/{$invoice->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('sales_invoices', ['id' => $invoice->id]);
    }

    public function test_posted_purchase_invoice_cannot_be_hard_deleted(): void
    {
        $env = $this->setupTenant();

        $bill = PurchaseInvoice::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'warehouse_id' => $env['warehouse']->id,
            'invoice_id' => 'PI-IMMUT-1',
            'vendor_id' => $env['vendor']->id,
            'purchase_date' => now()->toDateString(),
            'total_amount' => 600,
            'status' => 0,
            'created_by' => $env['user']->id,
        ]);
        PurchaseInvoiceItem::create(['invoice_id' => $bill->id, 'product_id' => $env['product']->id, 'quantity' => 1, 'price' => 600]);

        // Post the bill
        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post("/purchase-invoices/{$bill->id}/post")
            ->assertRedirect();

        $this->assertEquals(1, $bill->fresh()->status);

        // Attempting to delete the posted bill must fail with 422
        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->delete("/purchase-invoices/{$bill->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('purchase_invoices', ['id' => $bill->id]);
    }

    public function test_customer_with_financial_history_cannot_be_deleted(): void
    {
        $env = $this->setupTenant();

        CustomerPayment::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'customer_id' => $env['customer']->id,
            'amount' => 500,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->delete("/accounting/customers/{$env['customer']->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('account_customers', ['id' => $env['customer']->id]);
    }

    public function test_vendor_with_financial_history_cannot_be_deleted(): void
    {
        $env = $this->setupTenant();

        VendorPayment::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'vendor_id' => $env['vendor']->id,
            'amount' => 300,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->delete("/accounting/vendors/{$env['vendor']->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('account_vendors', ['id' => $env['vendor']->id]);
    }

    public function test_financial_balance_service_reconciliation(): void
    {
        $env = $this->setupTenant();

        // Create invoice + payment + credit note
        $invoice = SalesInvoice::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'warehouse_id' => $env['warehouse']->id,
            'invoice_id' => 'SI-REC-1',
            'customer_id' => $env['customer']->id,
            'issue_date' => now()->toDateString(),
            'total_amount' => 1000,
            'status' => 'posted',
            'created_by' => $env['user']->id,
        ]);

        CustomerPayment::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'customer_id' => $env['customer']->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        AccountCreditNote::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'customer_id' => $env['customer']->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'date' => now()->toDateString(),
            'status' => 'applied',
            'created_by' => $env['user']->id,
        ]);

        /** @var FinancialBalanceService $service */
        $service = app(FinancialBalanceService::class);

        // Intentionally tamper with the stored balance cache to simulate drift
        $env['customer']->update(['balance' => '9999.00']);

        // Run reconciliation
        $stats = $service->reconcileWorkspaceBalances($env['ws']->id);

        $this->assertEquals(1, $stats['customers_checked']);
        $this->assertEquals(1, $stats['customers_fixed']);

        // Assert true balance is 1000 - 400 - 100 = 500.00
        $this->assertEquals('500.00', $env['customer']->fresh()->balance);

        // Second run should find 0 discrepancies
        $statsSecond = $service->reconcileWorkspaceBalances($env['ws']->id);
        $this->assertEquals(0, $statsSecond['customers_fixed']);
    }
}
