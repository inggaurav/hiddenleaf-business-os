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

class PosReturnCumulativeTest extends TestCase
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
            'name' => 'Item X',
            'sku' => 'ITM-X',
            'type' => 'product',
            'sale_price' => 20.00,
            'purchase_price' => 10.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '10.0000']);

        // Buy 3 units
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

    public function test_cumulative_returns_cannot_exceed_quantity_sold(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        // Return 1
        $r1 = $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $this->sale->id,
            'reason' => 'First return',
            'items' => [['pos_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'quantity' => 1]],
        ]);
        $r1->assertOk();
        $ret1Id = $r1->json('return.id');
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$ret1Id}/approve")->assertOk();
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$ret1Id}/complete")->assertOk();

        // Return 2
        $r2 = $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $this->sale->id,
            'reason' => 'Second return',
            'items' => [['pos_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'quantity' => 1]],
        ]);
        $r2->assertOk();
        $ret2Id = $r2->json('return.id');
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$ret2Id}/approve")->assertOk();
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$ret2Id}/complete")->assertOk();

        // Return 3 (Remaining: 1 unit)
        $r3 = $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $this->sale->id,
            'reason' => 'Third return',
            'items' => [['pos_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'quantity' => 1]],
        ]);
        $r3->assertOk();
        $ret3Id = $r3->json('return.id');
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$ret3Id}/approve")->assertOk();
        $this->actingAs($this->user)->withSession($session)->postJson("/pos/returns/{$ret3Id}/complete")->assertOk();

        // Total returned = 3. Attempting to return 1 more must fail
        $this->actingAs($this->user)->withSession($session)->postJson('/pos/returns', [
            'pos_sale_id' => $this->sale->id,
            'reason' => 'Fourth return attempt (overflow)',
            'items' => [['pos_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'quantity' => 1]],
        ])->assertServerError();
    }
}
