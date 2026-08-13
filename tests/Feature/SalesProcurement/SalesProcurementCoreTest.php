<?php

namespace Tests\Feature\SalesProcurement;

use App\Models\Organization;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturn;
use App\Models\SalesProposal;
use App\Models\User;
use App\Models\Warehouse;
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

        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post('/transfers', [
                'from_warehouse' => $wh1->id,
                'to_warehouse' => $wh2->id,
                'product_id' => 10,
                'quantity' => 25,
                'date' => now()->toDateString(),
            ]);

        $response->assertRedirect('/transfers');
        $this->assertDatabaseHas('transfers', [
            'from_warehouse' => $wh1->id,
            'to_warehouse' => $wh2->id,
            'quantity' => 25,
        ]);
    }

    public function test_purchase_invoice_and_return_lifecycle(): void
    {
        $wh = Warehouse::create([
            'name' => 'Depot Alpha',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);

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
                    ['item_name' => 'Server Rack Unit', 'quantity' => 2, 'price' => 500.00],
                    ['item_name' => 'Cat6 Cable Reel', 'quantity' => 5, 'price' => 50.00],
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
                    ['item_name' => 'Defective Cable Reel', 'quantity' => 1, 'price' => 50.00],
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
    }

    public function test_sales_proposal_conversion_and_sales_invoice_return(): void
    {
        $wh = Warehouse::create([
            'name' => 'Depot Beta',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);

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
                    ['item_name' => 'Cloud Consulting Hours', 'quantity' => 10, 'price' => 150.00],
                ],
            ]);

        $response->assertRedirect('/sales-proposals');
        $proposal = SalesProposal::first();
        $this->assertEquals(1500.00, $proposal->total_amount);

        // 2. Accept proposal and convert to sales invoice
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
                    ['item_name' => 'Unused Consulting Hours', 'quantity' => 2, 'price' => 150.00],
                ],
            ]);

        $salesReturn = SalesInvoiceReturn::first();
        $this->assertNotNull($salesReturn);
        $this->assertEquals(300.00, $salesReturn->total_amount);

        $this->actingAs($this->user)->post("/sales-returns/{$salesReturn->id}/approve");
        $salesReturn->refresh();
        $this->assertEquals(1, $salesReturn->status);
    }
}
