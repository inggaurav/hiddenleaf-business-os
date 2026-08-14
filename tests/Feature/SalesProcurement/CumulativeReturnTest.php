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
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CumulativeReturnTest extends TestCase
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

        return compact('user', 'org', 'ws', 'warehouse', 'product');
    }

    public function test_sales_return_second_return_blocked_when_cumulative_exceeds_original(): void
    {
        $env = $this->setupEnv();

        $invoice = SalesInvoice::create(['organization_id' => $env['org']->id, 'workspace_id' => $env['ws']->id, 'warehouse_id' => $env['warehouse']->id, 'issue_date' => now()->toDateString(), 'total_amount' => 100, 'status' => 1, 'created_by' => $env['user']->id, 'invoice_id' => 'SI-CR-1']);
        SalesInvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $env['product']->id, 'quantity' => 10, 'price' => 10]);

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->postJson('/sales-returns', [
                'sales_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [['product_id' => $env['product']->id, 'quantity' => 6, 'price' => 10]],
            ])->assertRedirect();

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->postJson('/sales-returns', [
                'sales_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [['product_id' => $env['product']->id, 'quantity' => 6, 'price' => 10]],
            ])->assertStatus(422);
    }

    public function test_purchase_return_second_return_blocked_when_cumulative_exceeds_original(): void
    {
        $env = $this->setupEnv();

        $invoice = PurchaseInvoice::create(['organization_id' => $env['org']->id, 'workspace_id' => $env['ws']->id, 'warehouse_id' => $env['warehouse']->id, 'purchase_date' => now()->toDateString(), 'total_amount' => 50, 'status' => 1, 'created_by' => $env['user']->id, 'invoice_id' => 'PI-CR-1']);
        PurchaseInvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $env['product']->id, 'quantity' => 10, 'price' => 5]);

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->postJson('/purchase-returns', [
                'purchase_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [['product_id' => $env['product']->id, 'quantity' => 6, 'price' => 5]],
            ])->assertRedirect();

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->postJson('/purchase-returns', [
                'purchase_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [['product_id' => $env['product']->id, 'quantity' => 6, 'price' => 5]],
            ])->assertStatus(422);
    }

    public function test_valid_second_return_accepted_when_within_limit(): void
    {
        $env = $this->setupEnv();

        $invoice = SalesInvoice::create(['organization_id' => $env['org']->id, 'workspace_id' => $env['ws']->id, 'warehouse_id' => $env['warehouse']->id, 'issue_date' => now()->toDateString(), 'total_amount' => 100, 'status' => 1, 'created_by' => $env['user']->id, 'invoice_id' => 'SI-CR-2']);
        SalesInvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $env['product']->id, 'quantity' => 10, 'price' => 10]);

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->postJson('/sales-returns', [
                'sales_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [['product_id' => $env['product']->id, 'quantity' => 4, 'price' => 10]],
            ])->assertRedirect();

        $this->actingAs($env['user'])
            ->withSession(['active_organization_id' => $env['org']->id, 'active_workspace_id' => $env['ws']->id])
            ->postJson('/sales-returns', [
                'sales_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [['product_id' => $env['product']->id, 'quantity' => 4, 'price' => 10]],
            ])->assertRedirect();
    }
}
