<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosInventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Workspace $workspace;
    private Warehouse $warehouse;
    private BillingCounter $counter;
    private ProductServiceItem $physicalProduct;
    private ProductServiceItem $serviceItem;

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
            'name' => 'Outlet',
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

        $this->physicalProduct = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Physical Goods',
            'sku' => 'PHY-01',
            'type' => 'product',
            'sale_price' => 100.00,
            'purchase_price' => 50.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->physicalProduct->id, 'quantity' => '10.0000']);

        $this->serviceItem = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Installation Labor',
            'sku' => 'SRV-01',
            'type' => 'service',
            'sale_price' => 50.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_pos_sale_and_return_use_canonical_stock_movements(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        // 1. Checkout physical product + service item
        $this->actingAs($this->user)->withSession($session)->postJson('/pos/store', [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->physicalProduct->id, 'quantity' => 2],
                ['product_id' => $this->serviceItem->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $sale = PosSale::with('items')->sole();

        // 2. Physical product has a pos_sale movement with direction -1
        $saleMovement = StockMovement::where('reference_type', 'pos_sale')
            ->where('reference_id', $sale->id)
            ->where('product_id', $this->physicalProduct->id)
            ->sole();

        $this->assertSame('pos_sale', $saleMovement->type);
        $this->assertEquals(-1, $saleMovement->direction);
        $this->assertEquals(2, (float) $saleMovement->quantity);

        // 3. Service item NEVER creates a stock movement
        $this->assertFalse(StockMovement::where('product_id', $this->serviceItem->id)->exists());

        // 4. Return physical product
        $saleItem = $sale->items->where('product_id', $this->physicalProduct->id)->first();
        $retRes = $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $sale->id,
            'reason' => 'Defective item',
            'items' => [
                ['pos_sale_item_id' => $saleItem->id, 'product_id' => $this->physicalProduct->id, 'quantity' => 1],
            ],
        ]);
        $retRes->assertOk();
        $returnId = $retRes->json('return.id');

        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$returnId}/complete")->assertOk();

        // 5. Return creates pos_return movement with direction +1
        $returnMovement = StockMovement::where('reference_type', 'pos_return')
            ->where('reference_id', $returnId)
            ->sole();

        $this->assertSame('pos_return', $returnMovement->type);
        $this->assertEquals(1, $returnMovement->direction);
        $this->assertEquals(1, (float) $returnMovement->quantity);
    }
}
