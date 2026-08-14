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

class PosTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private Organization $orgA;

    private Workspace $wsA;

    private Warehouse $whA;

    private BillingCounter $counterA;

    private ProductServiceItem $productA;

    private PosSale $saleA;

    private User $userB;

    private Organization $orgB;

    private Workspace $wsB;

    private Warehouse $whB;

    private BillingCounter $counterB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $plan = Plan::create([
            'name' => 'Enterprise',
            'status' => true,
            'modules' => ['pos', 'productservice'],
        ]);

        // Tenant A
        $this->userA = User::factory()->create(['role' => 'company_admin']);
        $this->orgA = Organization::factory()->create(['owner_id' => $this->userA->id, 'plan_id' => $plan->id]);
        $this->wsA = Workspace::factory()->create(['organization_id' => $this->orgA->id, 'created_by' => $this->userA->id]);
        $this->orgA->members()->attach($this->userA, ['role' => 'owner']);
        $this->wsA->members()->attach($this->userA);
        UserActiveModule::create(['workspace_id' => $this->wsA->id, 'module_name' => 'pos']);
        UserActiveModule::create(['workspace_id' => $this->wsA->id, 'module_name' => 'productservice']);

        $this->whA = Warehouse::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'name' => 'Outlet A']);
        $this->counterA = BillingCounter::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'warehouse_id' => $this->whA->id, 'name' => 'Counter A', 'counter_number' => 'CA-01']);
        $this->productA = ProductServiceItem::create(['organization_id' => $this->orgA->id, 'workspace_id' => $this->wsA->id, 'name' => 'Prod A', 'sku' => 'PA-01', 'type' => 'product', 'sale_price' => 100, 'purchase_price' => 50, 'is_active' => true]);
        WarehouseStock::create(['warehouse_id' => $this->whA->id, 'product_id' => $this->productA->id, 'quantity' => '10.0000']);

        $this->saleA = PosSale::create([
            'organization_id' => $this->orgA->id,
            'workspace_id' => $this->wsA->id,
            'sale_number' => 'POS-A-001',
            'billing_counter_id' => $this->counterA->id,
            'warehouse_id' => $this->whA->id,
            'cashier_id' => $this->userA->id,
            'subtotal' => '100.0000',
            'tax_amount' => '0.0000',
            'discount_amount' => '0.0000',
            'total' => '100.0000',
            'payment_method' => 'cash',
            'status' => 'completed',
            'idempotency_key' => 'sale-a-key',
            'created_by' => $this->userA->id,
        ]);

        // Tenant B
        $this->userB = User::factory()->create(['role' => 'company_admin']);
        $this->orgB = Organization::factory()->create(['owner_id' => $this->userB->id, 'plan_id' => $plan->id]);
        $this->wsB = Workspace::factory()->create(['organization_id' => $this->orgB->id, 'created_by' => $this->userB->id]);
        $this->orgB->members()->attach($this->userB, ['role' => 'owner']);
        $this->wsB->members()->attach($this->userB);
        UserActiveModule::create(['workspace_id' => $this->wsB->id, 'module_name' => 'pos']);
        UserActiveModule::create(['workspace_id' => $this->wsB->id, 'module_name' => 'productservice']);

        $this->whB = Warehouse::create(['organization_id' => $this->orgB->id, 'workspace_id' => $this->wsB->id, 'name' => 'Outlet B']);
        $this->counterB = BillingCounter::create(['organization_id' => $this->orgB->id, 'workspace_id' => $this->wsB->id, 'warehouse_id' => $this->whB->id, 'name' => 'Counter B', 'counter_number' => 'CB-01']);
    }

    public function test_tenant_b_cannot_access_tenant_a_pos_resources(): void
    {
        $sessionB = [
            'active_organization_id' => $this->orgB->id,
            'active_workspace_id' => $this->wsB->id,
        ];

        // 1. Tenant B cannot view Tenant A's sale
        $this->actingAs($this->userB)->withSession($sessionB)
            ->get("/pos/orders/{$this->saleA->id}")
            ->assertNotFound();

        // 2. Tenant B cannot print Tenant A's receipt
        $this->actingAs($this->userB)->withSession($sessionB)
            ->get("/pos/orders/{$this->saleA->id}/print")
            ->assertNotFound();

        // 3. Tenant B cannot checkout using Tenant A's product
        $this->actingAs($this->userB)->withSession($sessionB)
            ->postJson('/pos/store', [
                'billing_counter_id' => $this->counterB->id,
                'warehouse_id' => $this->whB->id,
                'payment_method' => 'cash',
                'items' => [['product_id' => $this->productA->id, 'quantity' => 1]],
            ])
            ->assertNotFound();

        // 4. Tenant B cannot checkout using Tenant A's counter
        $this->actingAs($this->userB)->withSession($sessionB)
            ->postJson('/pos/store', [
                'billing_counter_id' => $this->counterA->id,
                'warehouse_id' => $this->whB->id,
                'payment_method' => 'cash',
                'items' => [['product_id' => $this->productA->id, 'quantity' => 1]],
            ])
            ->assertNotFound();

        // 5. Tenant B cannot modify Tenant A's billing counter
        $this->actingAs($this->userB)->withSession($sessionB)
            ->put("/pos/billing-counters/{$this->counterA->id}", ['name' => 'Compromised'])
            ->assertNotFound();
    }
}
