<?php

namespace Tests\Feature\POS;

use App\Domain\POS\PosCheckoutService;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Workspace $workspace;
    private Warehouse $warehouse;
    private BillingCounter $counter;
    private ProductServiceItem $product;

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

        $this->product = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Mouse',
            'sku' => 'MS-01',
            'type' => 'product',
            'sale_price' => 30.00,
            'purchase_price' => 15.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => '10.0000',
        ]);
    }

    public function test_checkout_is_idempotent_with_same_key(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        $payload = [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'idempotency_key' => 'idem-key-123',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ];

        // 1. First checkout attempt
        $res1 = $this->actingAs($this->user)->withSession($session)->postJson('/pos/store', $payload);
        $res1->assertOk();
        $saleId1 = $res1->json('sale.id');

        // Verify stock is now 8
        $this->assertEquals(8, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('quantity'));
        $this->assertEquals(1, PosSale::count());

        // 2. Second checkout attempt with identical key (simulating double-click or network retry)
        $res2 = $this->actingAs($this->user)->withSession($session)->postJson('/pos/store', $payload);
        $res2->assertOk();
        $saleId2 = $res2->json('sale.id');

        // Both calls return the exact same sale
        $this->assertEquals($saleId1, $saleId2);

        // Stock is NOT decremented twice — still 8
        $this->assertEquals(8, (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('quantity'));
        $this->assertEquals(1, PosSale::count());
    }
}
