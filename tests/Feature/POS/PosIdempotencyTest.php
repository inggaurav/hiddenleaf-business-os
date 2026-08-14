<?php

namespace Tests\Feature\POS;

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

    private array $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = $this->makeTenant('A');
    }

    public function test_checkout_is_idempotent_with_same_key_and_fingerprint(): void
    {
        $payload = $this->payload($this->tenant, 'idem-key-123', '2.0000');
        $res1 = $this->request($this->tenant, $payload)->assertOk();
        $saleId1 = $res1->json('sale.id');

        $this->assertEquals(8, (float) $this->stock($this->tenant));
        $this->assertSame(1, PosSale::where('workspace_id', $this->tenant['workspace']->id)->count());

        $res2 = $this->request($this->tenant, $payload)->assertOk();
        $this->assertSame($saleId1, $res2->json('sale.id'));
        $this->assertEquals(8, (float) $this->stock($this->tenant));
        $this->assertSame(1, PosSale::where('workspace_id', $this->tenant['workspace']->id)->count());
    }

    public function test_same_key_with_different_cart_is_conflict(): void
    {
        $this->request($this->tenant, $this->payload($this->tenant, 'same-key-different-cart', '1.0000'))->assertOk();

        $this->request($this->tenant, $this->payload($this->tenant, 'same-key-different-cart', '2.0000'))
            ->assertStatus(409);

        $this->assertSame(1, PosSale::where('workspace_id', $this->tenant['workspace']->id)->count());
        $this->assertEquals(9, (float) $this->stock($this->tenant));
    }

    public function test_same_idempotency_key_is_independent_between_workspaces(): void
    {
        $tenantB = $this->makeTenant('B');

        $saleA = $this->request($this->tenant, $this->payload($this->tenant, 'shared-key', '1.0000'))->assertOk()->json('sale.id');
        $saleB = $this->request($tenantB, $this->payload($tenantB, 'shared-key', '1.0000'))->assertOk()->json('sale.id');

        $this->assertNotSame($saleA, $saleB);
        $this->assertSame(2, PosSale::where('idempotency_key', 'shared-key')->count());
        $this->assertEquals(9, (float) $this->stock($this->tenant));
        $this->assertEquals(9, (float) $this->stock($tenantB));
    }

    private function makeTenant(string $suffix): array
    {
        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Enterprise '.$suffix, 'status' => true, 'modules' => ['pos', 'productservice', 'account'], 'created_by' => $user->id]);
        $organization = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'created_by' => $user->id]);
        $organization->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);
        foreach (['pos', 'productservice', 'account'] as $module) {
            UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => $module]);
        }
        $warehouse = Warehouse::create(['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'name' => 'Outlet '.$suffix, 'created_by' => $user->id]);
        $counter = BillingCounter::create(['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'warehouse_id' => $warehouse->id, 'name' => 'Counter '.$suffix, 'counter_number' => 'C-'.$suffix, 'created_by' => $user->id]);
        $product = ProductServiceItem::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Mouse '.$suffix,
            'sku' => 'MS-'.$suffix,
            'type' => 'product',
            'sale_price' => 30.00,
            'purchase_price' => 15.00,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => '10.0000']);

        return compact('user', 'organization', 'workspace', 'warehouse', 'counter', 'product');
    }

    private function payload(array $tenant, string $key, string $quantity): array
    {
        return [
            'billing_counter_id' => $tenant['counter']->id,
            'warehouse_id' => $tenant['warehouse']->id,
            'payment_method' => 'cash',
            'idempotency_key' => $key,
            'items' => [['product_id' => $tenant['product']->id, 'quantity' => $quantity]],
        ];
    }

    private function request(array $tenant, array $payload)
    {
        return $this->actingAs($tenant['user'])->withSession([
            'active_organization_id' => $tenant['organization']->id,
            'active_workspace_id' => $tenant['workspace']->id,
        ])->postJson('/pos/store', $payload);
    }

    private function stock(array $tenant): string
    {
        return (string) WarehouseStock::where('warehouse_id', $tenant['warehouse']->id)
            ->where('product_id', $tenant['product']->id)
            ->value('quantity');
    }
}
