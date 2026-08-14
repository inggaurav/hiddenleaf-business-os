<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosDiscount;
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

class PosCheckoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Workspace $workspace;
    private Warehouse $warehouse;
    private BillingCounter $counter;
    private ProductServiceItem $productA;
    private ProductServiceItem $productB;
    private ProductServiceItem $serviceC;
    private PosDiscount $discount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create([
            'name' => 'Enterprise',
            'status' => true,
            'modules' => ['pos', 'productservice', 'account'],
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

        $this->productA = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Product A',
            'sku' => 'PROD-A',
            'type' => 'product',
            'sale_price' => 100.00,
            'purchase_price' => 60.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productA->id, 'quantity' => '10.0000']);

        $this->productB = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Product B',
            'sku' => 'PROD-B',
            'type' => 'product',
            'sale_price' => 50.00,
            'purchase_price' => 30.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productB->id, 'quantity' => '5.0000']);

        $this->serviceC = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Service C',
            'sku' => 'SERV-C',
            'type' => 'service',
            'sale_price' => 80.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $this->discount = PosDiscount::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => '10% Storewide',
            'type' => 'percentage',
            'value' => '10.0000',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_full_pos_checkout_return_and_stock_lifecycle(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        // 1. Checkout A qty 2 ($200), B qty 1 ($50), C qty 1 ($80) = subtotal $330
        // 10% discount = $33, total = $297
        $response = $this->actingAs($this->user)->withSession($session)->postJson('/pos/store', [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'discount_id' => $this->discount->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
                ['product_id' => $this->productB->id, 'quantity' => 1],
                ['product_id' => $this->serviceC->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);

        $sale = PosSale::with('items')->sole();
        $this->assertEquals(330.0, (float) $sale->subtotal);
        $this->assertEquals(33.0, (float) $sale->discount_amount);
        $this->assertEquals(297.0, (float) $sale->total);

        // Verify stock: A was 10 -> 8, B was 5 -> 4
        $this->assertEquals(8, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));
        $this->assertEquals(4, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productB->id)->value('quantity'));

        // Verify stock movements: 2 movements created (Product A and Product B only, NO movement for Service C)
        $movements = StockMovement::where('reference_type', 'pos_sale')->where('reference_id', $sale->id)->get();
        $this->assertCount(2, $movements);
        $this->assertTrue($movements->contains('product_id', $this->productA->id));
        $this->assertTrue($movements->contains('product_id', $this->productB->id));
        $this->assertFalse($movements->contains('product_id', $this->serviceC->id));

        // 2. Return Product A qty 1
        $saleItemA = $sale->items->where('product_id', $this->productA->id)->first();
        $returnResp = $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $sale->id,
            'reason' => 'Defective piece',
            'items' => [
                [
                    'pos_sale_item_id' => $saleItemA->id,
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $returnResp->assertOk()->assertJsonFragment(['success' => true]);
        $return = PosReturn::sole();
        $this->assertSame('draft', $return->status);

        // Draft return has NOT restored stock yet
        $this->assertEquals(8, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));

        // Approve return
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/approve")->assertOk();
        $this->assertSame('approved', $return->refresh()->status);

        // Complete return
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/complete")->assertOk();
        $this->assertSame('completed', $return->refresh()->status);

        // Stock for Product A restored: 8 -> 9
        $this->assertEquals(9, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));

        // Verify pos_return stock movement
        $returnMovement = StockMovement::where('reference_type', 'pos_return')->where('reference_id', $return->id)->sole();
        $this->assertEquals(1, $returnMovement->direction);
        $this->assertEquals($this->productA->id, $returnMovement->product_id);

        // 3. Second completion is idempotent (stock stays at 9)
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/complete")->assertOk();
        $this->assertEquals(9, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));

        // 4. Cumulative return attempt exceeding sold quantity (Attempting to return another 2 units of A when only 1 left available)
        $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $sale->id,
            'reason' => 'Excess return attempt',
            'items' => [
                [
                    'pos_sale_item_id' => $saleItemA->id,
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                ],
            ],
        ])->assertServerError();
    }
}
