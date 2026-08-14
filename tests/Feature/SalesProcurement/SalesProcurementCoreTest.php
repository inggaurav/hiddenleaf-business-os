<?php

namespace Tests\Feature\SalesProcurement;

use App\Models\Organization;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturn;
use App\Models\SalesProposal;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesProcurementCoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $org;

    protected Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create();
        $this->org = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->ws = Workspace::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
        ]);

        $this->user->organizations()->attach($this->org->id, ['role' => 'owner']);
        $this->user->workspaces()->attach($this->ws->id);
    }

    public function test_warehouse_crud_and_transfer(): void
    {
        $wh1 = Warehouse::create([
            'name' => 'Main Depot',
            'address' => '100 Logistics Way',
            'city' => 'Austin',
            'city_zip' => '78701',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);

        $wh2 = Warehouse::create([
            'name' => 'East Coast Hub',
            'address' => '200 Port Blvd',
            'city' => 'Newark',
            'city_zip' => '07101',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        $product = ProductServiceItem::create(['name' => 'Transfer Router', 'sku' => 'MOVE-1', 'type' => 'product', 'sale_price' => 100, 'purchase_price' => 50, 'organization_id' => $this->org->id, 'workspace_id' => $this->ws->id, 'created_by' => $this->user->id]);
        WarehouseStock::create(['product_id' => $product->id, 'warehouse_id' => $wh1->id, 'quantity' => 50]);

        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post('/transfers', [
                'from_warehouse' => $wh1->id,
                'to_warehouse' => $wh2->id,
                'product_id' => $product->id,
                'quantity' => 25,
                'date' => now()->toDateString(),
            ]);

        $response->assertRedirect('/transfers');
        $this->assertDatabaseHas('transfers', [
            'from_warehouse' => $wh1->id,
            'to_warehouse' => $wh2->id,
            'quantity' => 25,
        ]);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $wh1->id, 'product_id' => $product->id, 'quantity' => 25]);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $wh2->id, 'product_id' => $product->id, 'quantity' => 25]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_purchase_invoice_and_return_lifecycle(): void
    {
        $wh = Warehouse::create([
            'name' => 'Depot Alpha',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        $rack = ProductServiceItem::create(['name' => 'Server Rack Unit', 'sku' => 'RACK-1', 'type' => 'product', 'sale_price' => 750, 'purchase_price' => 500, 'organization_id' => $this->org->id, 'workspace_id' => $this->ws->id, 'created_by' => $this->user->id]);
        $cable = ProductServiceItem::create(['name' => 'Cat6 Cable Reel', 'sku' => 'CAT6-1', 'type' => 'product', 'sale_price' => 80, 'purchase_price' => 50, 'organization_id' => $this->org->id, 'workspace_id' => $this->ws->id, 'created_by' => $this->user->id]);

        // Create Purchase Invoice
        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post('/purchase-invoices', [
                'warehouse_id' => $wh->id,
                'purchase_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $rack->id, 'quantity' => 2, 'price' => 500.00],
                    ['product_id' => $cable->id, 'quantity' => 5, 'price' => 50.00],
                ],
            ]);

        $response->assertRedirect('/purchase-invoices');
        $this->assertDatabaseHas('purchase_invoices', [
            'warehouse_id' => $wh->id,
            'total_amount' => 1250.00,
            'status' => 0,
        ]);

        $invoice = PurchaseInvoice::first();

        // Post purchase invoice
        $this->actingAs($this->user)->post("/purchase-invoices/{$invoice->id}/post");
        $invoice->refresh();
        $this->assertEquals(1, $invoice->status);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $wh->id, 'product_id' => $rack->id, 'quantity' => 2]);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $wh->id, 'product_id' => $cable->id, 'quantity' => 5]);

        // Record Purchase Return
        $returnResp = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post('/purchase-returns', [
                'purchase_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $cable->id, 'quantity' => 1, 'price' => 50.00],
                ],
            ]);

        $returnResp->assertRedirect('/purchase-returns');
        $return = PurchaseReturn::first();
        $this->assertEquals(0, $return->status);

        // Approve and Complete return
        $this->actingAs($this->user)->post("/purchase-returns/{$return->id}/approve");
        $return->refresh();
        $this->assertEquals(1, $return->status);

        $this->actingAs($this->user)->post("/purchase-returns/{$return->id}/complete");
        $return->refresh();
        $this->assertEquals(2, $return->status);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $wh->id, 'product_id' => $cable->id, 'quantity' => 4]);
    }

    public function test_sales_proposal_conversion_and_sales_invoice_return(): void
    {
        $wh = Warehouse::create([
            'name' => 'Depot Beta',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        $consulting = ProductServiceItem::create(['name' => 'Cloud Consulting Hours', 'sku' => 'SVC-CLOUD', 'type' => 'service', 'sale_price' => 150, 'purchase_price' => 0, 'organization_id' => $this->org->id, 'workspace_id' => $this->ws->id, 'created_by' => $this->user->id]);

        // 1. Create Sales Proposal
        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post('/sales-proposals', [
                'customer_id' => 42,
                'issue_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $consulting->id, 'quantity' => 10, 'price' => 150.00],
                ],
            ]);

        $response->assertRedirect('/sales-proposals');
        $proposal = SalesProposal::first();
        $this->assertEquals(1500.00, $proposal->total_amount);

        // 2. Accept proposal and convert to sales invoice
        $this->actingAs($this->user)->post("/sales-proposals/{$proposal->id}/sent");
        $this->actingAs($this->user)->post("/sales-proposals/{$proposal->id}/accept");
        $proposal->refresh();
        $this->assertEquals(2, $proposal->status);

        $this->actingAs($this->user)->post("/sales-proposals/{$proposal->id}/convert-to-invoice");
        $proposal->refresh();
        $this->assertEquals(4, $proposal->status); // Converted

        $invoice = SalesInvoice::where('customer_id', 42)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(1500.00, $invoice->total_amount);

        // 3. Post invoice
        $this->actingAs($this->user)->post("/sales-invoices/{$invoice->id}/post");
        $invoice->refresh();
        $this->assertEquals(1, $invoice->status);

        // 4. Sales Return
        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post('/sales-returns', [
                'customer_id' => 42,
                'sales_invoice_id' => $invoice->id,
                'date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $consulting->id, 'quantity' => 2, 'price' => 150.00],
                ],
            ]);

        $salesReturn = SalesInvoiceReturn::first();
        $this->assertNotNull($salesReturn);
        $this->assertEquals(300.00, $salesReturn->total_amount);

        $this->actingAs($this->user)->post("/sales-returns/{$salesReturn->id}/approve");
        $salesReturn->refresh();
        $this->assertEquals(1, $salesReturn->status);
        $this->actingAs($this->user)->post("/sales-returns/{$salesReturn->id}/complete");
        $this->assertEquals(2, $salesReturn->refresh()->status);
    }

    public function test_invoice_catalog_endpoints_return_real_tenant_scoped_products_and_services(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Catalog Depot',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        $product = ProductServiceItem::create([
            'name' => 'Industrial Router',
            'sku' => 'RTR-100',
            'type' => 'product',
            'sale_price' => 499.99,
            'purchase_price' => 300,
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        $service = ProductServiceItem::create([
            'name' => 'Network Installation',
            'sku' => 'SVC-100',
            'type' => 'service',
            'sale_price' => 150,
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 12,
        ]);

        $session = [
            'active_organization_id' => $this->org->id,
            'active_workspace_id' => $this->ws->id,
        ];

        $this->actingAs($this->user)
            ->withSession($session)
            ->getJson(route('sales-invoices.warehouse.products', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Industrial Router',
                'sku' => 'RTR-100',
                'quantity' => '12.00',
            ])
            ->assertJsonMissing(['name' => 'Network Installation']);

        $this->actingAs($this->user)
            ->withSession($session)
            ->getJson(route('sales-invoices.services'))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Network Installation',
                'sku' => 'SVC-100',
            ])
            ->assertJsonMissing(['name' => 'Industrial Router']);

        $this->actingAs($this->user)
            ->withSession($session)
            ->getJson(route('sales-proposals.warehouse.products', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Industrial Router']);
    }

    public function test_catalog_endpoint_rejects_a_warehouse_from_another_tenant(): void
    {
        $otherOwner = User::factory()->create();
        $otherOrg = Organization::factory()->create(['owner_id' => $otherOwner->id]);
        $otherWorkspace = Workspace::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignWarehouse = Warehouse::create([
            'name' => 'Foreign Depot',
            'organization_id' => $otherOrg->id,
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherOwner->id,
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->getJson(route('sales-invoices.warehouse.products', ['warehouse_id' => $foreignWarehouse->id]))
            ->assertNotFound();
    }

    public function test_foreign_purchase_and_sales_invoices_cannot_be_read_mutated_or_posted(): void
    {
        $otherOwner = User::factory()->create();
        $otherOrg = Organization::factory()->create(['owner_id' => $otherOwner->id]);
        $otherWorkspace = Workspace::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherOwner->id]);
        $foreignWarehouse = Warehouse::create([
            'name' => 'Foreign Billing Depot',
            'organization_id' => $otherOrg->id,
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherOwner->id,
        ]);
        $foreignPurchase = PurchaseInvoice::create([
            'invoice_id' => 'PUR-FOREIGN-1',
            'warehouse_id' => $foreignWarehouse->id,
            'purchase_date' => today(),
            'total_amount' => 100,
            'status' => 0,
            'organization_id' => $otherOrg->id,
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherOwner->id,
        ]);
        $foreignSale = SalesInvoice::create([
            'invoice_id' => 'SAL-FOREIGN-1',
            'warehouse_id' => $foreignWarehouse->id,
            'issue_date' => today(),
            'total_amount' => 100,
            'status' => 0,
            'organization_id' => $otherOrg->id,
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherOwner->id,
        ]);
        $request = $this->actingAs($this->user)->withSession([
            'active_organization_id' => $this->org->id,
            'active_workspace_id' => $this->ws->id,
        ]);

        $request->get("/purchase-invoices/{$foreignPurchase->id}")->assertNotFound();
        $request->delete("/purchase-invoices/{$foreignPurchase->id}")->assertNotFound();
        $request->post("/purchase-invoices/{$foreignPurchase->id}/post")->assertNotFound();
        $request->get("/sales-invoices/{$foreignSale->id}")->assertNotFound();
        $request->delete("/sales-invoices/{$foreignSale->id}")->assertNotFound();
        $request->post("/sales-invoices/{$foreignSale->id}/post")->assertNotFound();

        $this->assertDatabaseHas('purchase_invoices', ['id' => $foreignPurchase->id, 'status' => 0]);
        $this->assertDatabaseHas('sales_invoices', ['id' => $foreignSale->id, 'status' => 0]);
    }

    public function test_product_purchase_sale_and_return_restore_stock_end_to_end(): void
    {
        $warehouse = Warehouse::create(['name' => 'Lifecycle Depot', 'organization_id' => $this->org->id, 'workspace_id' => $this->ws->id, 'created_by' => $this->user->id]);
        $product = ProductServiceItem::create(['name' => 'Lifecycle Product', 'sku' => 'FLOW-1', 'type' => 'product', 'sale_price' => 100, 'purchase_price' => 60, 'organization_id' => $this->org->id, 'workspace_id' => $this->ws->id, 'created_by' => $this->user->id]);
        $session = ['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id];

        $this->actingAs($this->user)->withSession($session)->post('/purchase-invoices', [
            'warehouse_id' => $warehouse->id,
            'purchase_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => 60]],
        ])->assertSessionHasNoErrors();
        $purchase = PurchaseInvoice::sole();
        $this->actingAs($this->user)->withSession($session)->post("/purchase-invoices/{$purchase->id}/post")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 10]);

        $this->actingAs($this->user)->withSession($session)->post('/sales-invoices', [
            'warehouse_id' => $warehouse->id,
            'issue_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'price' => 100]],
        ])->assertSessionHasNoErrors();
        $sale = SalesInvoice::sole();
        $this->actingAs($this->user)->withSession($session)->post("/sales-invoices/{$sale->id}/post")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 6]);

        $this->actingAs($this->user)->withSession($session)->post('/sales-returns', [
            'sales_invoice_id' => $sale->id,
            'date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 100]],
        ])->assertSessionHasNoErrors();
        $return = SalesInvoiceReturn::sole();
        $this->actingAs($this->user)->withSession($session)->post("/sales-returns/{$return->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($this->user)->withSession($session)->post("/sales-returns/{$return->id}/complete")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 8]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'purchase_received']);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale_issued']);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sales_return_received']);
    }

    public function test_invoice_catalog_endpoints_return_real_tenant_scoped_products_and_services(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Catalog Depot',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        $product = ProductServiceItem::create([
            'name' => 'Industrial Router',
            'sku' => 'RTR-100',
            'type' => 'product',
            'sale_price' => 499.99,
            'purchase_price' => 300,
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        $service = ProductServiceItem::create([
            'name' => 'Network Installation',
            'sku' => 'SVC-100',
            'type' => 'service',
            'sale_price' => 150,
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 12,
        ]);

        $session = [
            'active_organization_id' => $this->org->id,
            'active_workspace_id' => $this->ws->id,
        ];

        $this->actingAs($this->user)
            ->withSession($session)
            ->getJson(route('sales-invoices.warehouse.products', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Industrial Router',
                'sku' => 'RTR-100',
                'quantity' => '12.00',
            ])
            ->assertJsonMissing(['name' => 'Network Installation']);

        $this->actingAs($this->user)
            ->withSession($session)
            ->getJson(route('sales-invoices.services'))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Network Installation',
                'sku' => 'SVC-100',
            ])
            ->assertJsonMissing(['name' => 'Industrial Router']);

        $this->actingAs($this->user)
            ->withSession($session)
            ->getJson(route('sales-proposals.warehouse.products', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Industrial Router']);
    }

    public function test_catalog_endpoint_rejects_a_warehouse_from_another_tenant(): void
    {
        $otherOwner = User::factory()->create();
        $otherOrg = Organization::factory()->create(['owner_id' => $otherOwner->id]);
        $otherWorkspace = Workspace::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignWarehouse = Warehouse::create([
            'name' => 'Foreign Depot',
            'organization_id' => $otherOrg->id,
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherOwner->id,
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->getJson(route('sales-invoices.warehouse.products', ['warehouse_id' => $foreignWarehouse->id]))
            ->assertNotFound();
    }
}
