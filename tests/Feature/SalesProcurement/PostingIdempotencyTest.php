<?php

namespace Tests\Feature\SalesProcurement;

use App\Models\Organization;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setupEnv(): array
    {
        $user = User::factory()->create(['role' => 'company_admin']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        $warehouse = Warehouse::create(['name' => 'Wh', 'organization_id' => $org->id, 'workspace_id' => $ws->id, 'created_by' => $user->id]);
        $product = ProductServiceItem::create(['name' => 'Prod', 'sku' => 'P1', 'type' => 'product', 'sale_price' => 10, 'purchase_price' => 5, 'organization_id' => $org->id, 'workspace_id' => $ws->id, 'created_by' => $user->id]);

        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 20]);

        return compact('user', 'org', 'ws', 'warehouse', 'product');
    }

    public function test_posting_same_invoice_twice_does_not_double_decrement_stock(): void
    {
        $env = $this->setupEnv();

        $invoice = SalesInvoice::create(['organization_id' => $env['org']->id, 'workspace_id' => $env['ws']->id, 'warehouse_id' => $env['warehouse']->id, 'issue_date' => now()->toDateString(), 'total_amount' => 50, 'status' => 0, 'created_by' => $env['user']->id, 'invoice_id' => 'SI-POST-1']);
        SalesInvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $env['product']->id, 'quantity' => 5, 'price' => 10]);

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post("/sales-invoices/{$invoice->id}/post")
            ->assertRedirect();

        $stock = WarehouseStock::where('warehouse_id', $env['warehouse']->id)->where('product_id', $env['product']->id)->first();
        $this->assertEquals(15, $stock->quantity);

        // Second attempt must fail and NOT decrement stock further
        try {
            $this->actingAs($env['user'])
                ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
                ->post("/sales-invoices/{$invoice->id}/post");
        } catch (\Throwable $e) {
            // Expected RuntimeException: Only draft invoices can be posted.
        }

        $stock->refresh();
        $this->assertEquals(15, $stock->quantity);
    }

    public function test_posting_same_purchase_invoice_twice_does_not_double_increment_stock(): void
    {
        $env = $this->setupEnv();

        $invoice = PurchaseInvoice::create(['organization_id' => $env['org']->id, 'workspace_id' => $env['ws']->id, 'warehouse_id' => $env['warehouse']->id, 'purchase_date' => now()->toDateString(), 'total_amount' => 25, 'status' => 0, 'created_by' => $env['user']->id, 'invoice_id' => 'PI-POST-1']);
        PurchaseInvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $env['product']->id, 'quantity' => 5, 'price' => 5]);

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->post("/purchase-invoices/{$invoice->id}/post")
            ->assertRedirect();

        $stock = WarehouseStock::where('warehouse_id', $env['warehouse']->id)->where('product_id', $env['product']->id)->first();
        $this->assertEquals(25, $stock->quantity);

        // Second attempt must fail and NOT increment stock further
        try {
            $this->actingAs($env['user'])
                ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
                ->post("/purchase-invoices/{$invoice->id}/post");
        } catch (\Throwable $e) {
            // Expected RuntimeException: Only draft invoices can be posted.
        }

        $stock->refresh();
        $this->assertEquals(25, $stock->quantity);
    }
}
