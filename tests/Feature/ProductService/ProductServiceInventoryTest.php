<?php

namespace Tests\Feature\ProductService;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Workspace $workspace;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $this->plan = Plan::create(['name' => 'Business', 'modules' => ['productservice'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $this->plan->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->workspace->members()->attach($this->owner);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);
    }

    public function test_catalog_crud_and_stock_adjustments_are_tenant_scoped_and_audited(): void
    {
        $this->tenantRequest()->post('/product-service/categories', ['name' => 'Hardware', 'type' => 'product'])->assertSessionHasNoErrors();
        $this->tenantRequest()->post('/product-service', [
            'name' => 'Router',
            'sku' => 'RTR-1',
            'barcode' => '890000000001',
            'type' => 'product',
            'sale_price' => 150,
            'purchase_price' => 100,
            'reorder_level' => 2,
            'unit' => 'piece',
            'is_active' => true,
        ])->assertRedirect('/product-service');

        $product = ProductServiceItem::sole();
        $warehouse = Warehouse::create(['name' => 'Main', 'organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
        $this->tenantRequest()->post("/product-service/{$product->id}/adjust-stock", [
            'warehouse_id' => $warehouse->id,
            'quantity' => 12.5,
            'reason' => 'Opening stock',
        ])->assertSessionHasNoErrors();

        $this->assertSame('12.5000', WarehouseStock::sole()->quantity);
        $this->assertSame('12.5000', StockMovement::sole()->balance_after);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.adjusted', 'workspace_id' => $this->workspace->id]);

        $this->tenantRequest()->post("/product-service/{$product->id}/adjust-stock", [
            'warehouse_id' => $warehouse->id,
            'quantity' => -20,
            'reason' => 'Invalid correction',
        ])->assertServerError();
        $this->assertSame('12.5000', WarehouseStock::sole()->quantity);
    }

    public function test_cross_tenant_catalog_and_warehouse_idor_are_rejected(): void
    {
        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWorkspace = Workspace::factory()->create(['organization_id' => $foreignOrg->id, 'created_by' => $foreignOwner->id]);
        $foreignProduct = ProductServiceItem::create(['name' => 'Foreign', 'type' => 'product', 'sale_price' => 1, 'purchase_price' => 1, 'organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWorkspace->id]);
        $foreignWarehouse = Warehouse::create(['name' => 'Foreign', 'organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWorkspace->id]);

        $this->tenantRequest()->get("/product-service/{$foreignProduct->id}/edit")->assertNotFound();
        $this->tenantRequest()->put("/warehouses/{$foreignWarehouse->id}", ['name' => 'Attacked'])->assertNotFound();
        $this->assertDatabaseMissing('warehouses', ['id' => $foreignWarehouse->id, 'name' => 'Attacked']);
    }

    public function test_active_module_and_plan_entitlement_are_both_required(): void
    {
        UserActiveModule::where('workspace_id', $this->workspace->id)->delete();
        $this->tenantRequest()->get('/product-service')->assertForbidden();

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);
        $this->plan->update(['modules' => []]);
        $this->tenantRequest()->get('/product-service')->assertForbidden();
    }

    private function tenantRequest(): self
    {
        return $this->actingAs($this->owner)->withSession([
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ]);
    }
}
