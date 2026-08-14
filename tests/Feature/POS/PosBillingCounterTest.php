<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosBillingCounterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private Workspace $workspace;

    private Warehouse $warehouse;

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
            'name' => 'Main Outlet',
            'created_by' => $this->user->id,
        ]);

        $this->product = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Product 1',
            'sku' => 'P-01',
            'type' => 'product',
            'sale_price' => 50,
            'purchase_price' => 25,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '10.0000']);
    }

    public function test_counter_crud_and_deletion_guard(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        // 1. Create counter
        $this->actingAs($this->user)->withSession($session)->post('/pos/billing-counters', [
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Express Lane',
            'counter_number' => 'EXP-01',
        ])->assertSessionHasNoErrors();

        $counter = BillingCounter::sole();
        $this->assertSame('Express Lane', $counter->name);

        // 2. Update counter
        $this->actingAs($this->user)->withSession($session)->put("/pos/billing-counters/{$counter->id}", [
            'name' => 'Express Lane 1',
            'counter_number' => 'EXP-01-A',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Express Lane 1', $counter->fresh()->name);

        // 3. Record a transaction with this counter
        $this->actingAs($this->user)->withSession($session)->postJson('/pos/store', [
            'billing_counter_id' => $counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])->assertOk();

        // 4. Attempt to delete counter: it should be soft-deleted and marked inactive (not hard deleted)
        $this->actingAs($this->user)->withSession($session)->delete("/pos/billing-counters/{$counter->id}")->assertSessionHasNoErrors();

        $counter->refresh();
        $this->assertFalse($counter->is_active);
        $this->assertSoftDeleted('billing_counters', ['id' => $counter->id]);
    }
}
