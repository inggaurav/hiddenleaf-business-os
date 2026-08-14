<?php

namespace Tests\Feature\POS;

use App\Models\JournalEntry;
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
        $plan = Plan::create(['name' => 'Enterprise', 'status' => true, 'modules' => ['pos', 'productservice', 'account'], 'created_by' => $this->user->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);
        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);
        foreach (['pos', 'productservice', 'account'] as $module) {
            UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => $module]);
        }
        $this->warehouse = Warehouse::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Main Outlet', 'created_by' => $this->user->id]);
        $this->counter = BillingCounter::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'warehouse_id' => $this->warehouse->id, 'name' => 'Counter 1', 'counter_number' => 'C-01', 'created_by' => $this->user->id]);
        $this->productA = $this->item('Product A', 'PROD-A', 'product', 100, 60);
        $this->productB = $this->item('Product B', 'PROD-B', 'product', 50, 30);
        $this->serviceC = $this->item('Service C', 'SERV-C', 'service', 80, 0);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productA->id, 'quantity' => '10.0000']);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productB->id, 'quantity' => '5.0000']);
        $this->discount = PosDiscount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => '10% Storewide', 'type' => 'percentage', 'value' => '10.0000', 'created_by' => $this->user->id]);
    }

    public function test_full_pos_checkout_return_stock_and_accounting_lifecycle(): void
    {
        $session = ['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id];
        $response = $this->actingAs($this->user)->withSession($session)->postJson('/pos/store', [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'discount_id' => $this->discount->id,
            'payment_method' => 'cash',
            'idempotency_key' => 'pos-full-lifecycle-1',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
                ['product_id' => $this->productB->id, 'quantity' => 1],
                ['product_id' => $this->serviceC->id, 'quantity' => 1],
            ],
        ])->assertOk()->assertJsonFragment(['success' => true]);

        $sale = PosSale::with('items')->sole();
        $this->assertEquals(330.0, (float) $sale->subtotal);
        $this->assertEquals(33.0, (float) $sale->discount_amount);
        $this->assertEquals(297.0, (float) $sale->total);
        $this->assertNotNull($sale->journal_entry_id);
        $this->assertSame('posted', JournalEntry::findOrFail($sale->journal_entry_id)->status);
        $this->assertSame('8.0000', WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));
        $this->assertSame('4.0000', WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productB->id)->value('quantity'));

        $movements = StockMovement::where('reference_type', 'pos_sale')->where('reference_id', $sale->id)->get();
        $this->assertCount(2, $movements);
        $this->assertFalse($movements->contains('product_id', $this->serviceC->id));

        $saleItemA = $sale->items->where('product_id', $this->productA->id)->firstOrFail();
        $returnResp = $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $sale->id,
            'reason' => 'Defective piece',
            'refund_method' => 'cash',
            'items' => [['pos_sale_item_id' => $saleItemA->id, 'product_id' => $this->productA->id, 'quantity' => 1]],
        ])->assertOk();
        $return = PosReturn::findOrFail($returnResp->json('return.id'));
        $this->assertSame('draft', $return->status);
        $this->assertSame('8.0000', WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));

        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/approve")->assertOk();
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/complete")->assertOk();
        $return->refresh();
        $this->assertSame('completed', $return->status);
        $this->assertNotNull($return->journal_entry_id);
        $this->assertSame('posted', JournalEntry::findOrFail($return->journal_entry_id)->status);
        $this->assertSame('9.0000', WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));

        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$return->id}/complete")->assertOk();
        $this->assertSame('9.0000', WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('quantity'));
        $this->assertSame(1, StockMovement::where('reference_type', 'pos_return')->where('reference_id', $return->id)->count());

        $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $sale->id,
            'reason' => 'Excess return attempt',
            'refund_method' => 'cash',
            'items' => [['pos_sale_item_id' => $saleItemA->id, 'product_id' => $this->productA->id, 'quantity' => 2]],
        ])->assertServerError();
    }

    private function item(string $name, string $sku, string $type, int $salePrice, int $purchasePrice): ProductServiceItem
    {
        return ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => $name,
            'sku' => $sku,
            'type' => $type,
            'sale_price' => $salePrice,
            'purchase_price' => $purchasePrice,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }
}
