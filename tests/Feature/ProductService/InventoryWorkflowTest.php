<?php

namespace Tests\Feature\ProductService;

use App\Domain\Inventory\StockAdjustmentService;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProductServiceItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = \App\Models\Plan::create(['name' => 'Enterprise', 'status' => true, 'modules' => ['productservice', 'account', 'pos'], 'created_by' => $this->user->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        \App\Models\UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);
    }

    public function test_warehouse_crud_and_deletion_guards(): void
    {
        // 1. Create warehouse
        $storeResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('warehouses.store'), [
                'name' => 'North Distribution Center',
                'code' => 'WH-NORTH',
                'address' => '100 North Blvd',
                'city' => 'Metropolis',
                'city_zip' => '10001',
                'phone' => '+1-555-0199',
                'is_active' => true,
            ]);
        $storeResponse->assertRedirect(route('warehouses.index'));
        $warehouse = Warehouse::where('code', 'WH-NORTH')->firstOrFail();
        $this->assertEquals('Metropolis', $warehouse->city);

        // 2. Update warehouse
        $updateResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->put(route('warehouses.update', $warehouse), [
                'name' => 'North Regional Center',
                'code' => 'WH-NORTH-R',
                'address' => '100 North Blvd Suite 2',
                'city' => 'Metropolis',
                'city_zip' => '10002',
                'phone' => '+1-555-0199',
                'is_active' => true,
            ]);
        $updateResponse->assertRedirect(route('warehouses.index'));
        $this->assertEquals('North Regional Center', $warehouse->fresh()->name);

        // 3. Deletion Guard: attach stock and verify 422
        $product = ProductServiceItem::create([
            'name' => 'Solar Battery',
            'sku' => 'BAT-001',
            'type' => 'product',
            'sale_price' => 200.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $deleteResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->delete(route('warehouses.destroy', $warehouse));

        $deleteResponse->assertStatus(422);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_stock_adjustment_creates_canonical_ledger_movement(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'South Depot',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'USB Adapter',
            'sku' => 'USB-ADP',
            'type' => 'product',
            'sale_price' => 10.00,
            'purchase_price' => 4.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $service = app(StockAdjustmentService::class);

        // Increase stock
        $m1 = $service->adjust($product, $warehouse, 100, 'Initial receipt', $this->user, 'opening');
        $this->assertEquals(100.0, (float) $m1->quantity);
        $this->assertEquals(1, $m1->direction);
        $this->assertEquals(100.0, (float) $m1->balance_after);
        $this->assertEquals(400.00, (float) $m1->total_cost);

        // Decrease stock
        $m2 = $service->adjust($product, $warehouse, -20, 'Damaged during inspection', $this->user, 'adjustment_out');
        $this->assertEquals(20.0, (float) $m2->quantity);
        $this->assertEquals(-1, $m2->direction);
        $this->assertEquals(80.0, (float) $m2->balance_after);

        $stock = WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->first();
        $this->assertEquals(80.0, (float) $stock->quantity);
    }

    public function test_service_items_reject_inventory_adjustments(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Service Hub',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $service = ProductServiceItem::create([
            'name' => 'Consulting Hour',
            'sku' => 'CON-001',
            'type' => 'service',
            'sale_price' => 150.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Services cannot hold warehouse inventory.');

        app(StockAdjustmentService::class)->adjust($service, $warehouse, 10, 'Test', $this->user);
    }

    public function test_stock_movement_ledger_history_and_filters(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'East Dock',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Power Supply 650W',
            'sku' => 'PSU-650',
            'type' => 'product',
            'sale_price' => 85.00,
            'purchase_price' => 50.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        app(StockAdjustmentService::class)->adjust($product, $warehouse, 30, 'Bulk delivery', $this->user, 'purchase');

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('inventory.movements', ['product_id' => $product->id]));

        $response->assertOk();
    }
}
