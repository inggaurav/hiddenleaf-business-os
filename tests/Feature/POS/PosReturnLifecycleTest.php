<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosReturnLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Workspace $workspace;
    private Warehouse $warehouse;
    private BillingCounter $counter;
    private ProductServiceItem $product;
    private PosSale $sale;
    private PosSaleItem $saleItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create([
            'name' => 'Enterprise',
            'status' => true,
            'modules' => ['pos', 'productservice'],
            'created_by' => $this->user->id,
        ]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'pos']);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);

        $this->warehouse = Warehouse::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Main Outlet',
            'created_by' => $this->user->id,
        ]);

        $this->counter = BillingCounter::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Counter 1',
            'counter_number' => 'C-01',
            'created_by' => $this->user->id,
        ]);

        $this->product = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Headphones',
            'sku' => 'HP-01',
            'type' => 'product',
            'sale_price' => 150.00,
            'purchase_price' => 75.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '10.0000']);

        // Execute sale
        $this->actingAs($this->user)->withSession([
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ])->postJson('/pos/store', [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ]);

        $this->sale = PosSale::with('items')->sole();
        $this->saleItem = $this->sale->items->first();
    }

    public function test_return_lifecycle_states_and_stock(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        // 1. Initial stock is 7 (10 - 3)
        $this->assertEquals(7, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('quantity'));

        // 2. Create draft return for 1 unit
        $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $this->sale->id,
            'reason' => 'Broken cable',
            'items' => [
                ['pos_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $return = PosReturn::sole();
        $this->assertSame('draft', $return->status);

        // Draft state does NOT restore stock (still 7)
        $this->assertEquals(7, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('quantity'));

        // 3. Approve return
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/approve")->assertOk();
        $this->assertSame('approved', $return->refresh()->status);

        // Approved state does NOT restore stock (still 7)
        $this->assertEquals(7, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('quantity'));

        // 4. Complete return -> restores stock to 8
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/complete")->assertOk();
        $this->assertSame('completed', $return->refresh()->status);
        $this->assertEquals(8, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('quantity'));

        // 5. Cannot delete completed return
        $this->actingAs($this->user)->withSession($session)->delete("/pos/returns/{$return->id}");
        $this->assertDatabaseHas('pos_returns', ['id' => $return->id]);
    }
}
