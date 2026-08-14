<?php

namespace Tests\Feature\Modules;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Workspace $workspace;

    private Warehouse $warehouse;

    private ProductServiceItem $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $plan = Plan::create(['name' => 'POS', 'modules' => ['pos', 'productservice'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach($this->owner);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'pos']);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);
        $this->warehouse = Warehouse::create(['name' => 'Shop', 'organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id]);
        $this->product = ProductServiceItem::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Tea', 'sku' => 'TEA-1', 'barcode' => '8901', 'type' => 'product', 'sale_price' => 100, 'purchase_price' => 50, 'is_active' => true]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '10.0000']);
    }

    public function test_counter_checkout_receipt_and_return_lifecycle(): void
    {
        // 1. Create counter
        $this->request()->post('/pos/billing-counters', [
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Front Desk',
            'counter_number' => 'CTR-01',
        ])->assertSessionHasNoErrors();

        $counter = BillingCounter::sole();
        $this->assertSame('Front Desk', $counter->name);

        // 2. Lookup products
        $this->request()->getJson('/pos/products?query=8901')->assertOk()->assertJsonFragment(['sku' => 'TEA-1']);

        // 3. Checkout
        $response = $this->request()->postJson('/pos/store', [
            'billing_counter_id' => $counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);
        $response->assertOk()->assertJsonFragment(['success' => true]);

        $sale = PosSale::with('items')->firstOrFail();
        $this->assertEquals(200.0, (float) $sale->total);

        // Verify stock decremented from 10 to 8
        $stock = WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(8, (float) $stock->quantity);

        // 4. Create return request
        $returnResp = $this->request()->postJson('/pos/returns', [
            'pos_sale_id' => $sale->id,
            'reason' => 'Customer return',
            'items' => [
                [
                    'pos_sale_item_id' => $sale->items->first()->id,
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ]);
        $returnResp->assertOk()->assertJsonFragment(['success' => true]);

        $return = PosReturn::firstOrFail();
        $this->assertSame('draft', $return->status);

        // Approve return
        $this->request()->postJson("/pos/returns/{$return->id}/approve")->assertOk();
        $this->assertSame('approved', $return->refresh()->status);

        // Complete return
        $this->request()->postJson("/pos/returns/{$return->id}/complete")->assertOk();
        $this->assertSame('completed', $return->refresh()->status);

        // Verify stock restored from 8 to 9
        $stock->refresh();
        $this->assertEquals(9, (float) $stock->quantity);
    }

    public function test_insufficient_stock_and_cross_tenant_counter_are_rejected(): void
    {
        $counter = BillingCounter::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Counter 1',
            'counter_number' => 'C-01',
        ]);

        // Attempting to buy 20 when stock is 10
        $this->request()->postJson('/pos/store', [
            'billing_counter_id' => $counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 20],
            ],
        ])->assertServerError();

        $this->assertDatabaseCount('pos_sales', 0);

        // Cross-tenant counter rejection
        $other = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $otherWs = Workspace::factory()->create(['organization_id' => $other->id]);
        $foreignCounter = BillingCounter::create([
            'organization_id' => $other->id,
            'workspace_id' => $otherWs->id,
            'name' => 'Foreign',
            'counter_number' => 'FC-01',
        ]);

        $this->request()->put("/pos/billing-counters/{$foreignCounter->id}", ['name' => 'Hacked'])->assertNotFound();
    }

    private function request(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id]);
    }
}
