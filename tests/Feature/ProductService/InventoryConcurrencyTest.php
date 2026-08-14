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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
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

    public function test_concurrent_stock_decrements_prevent_negative_inventory(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Lock Warehouse',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Concurrent Item',
            'sku' => 'CNC-001',
            'type' => 'product',
            'sale_price' => 100.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $stockService = app(StockAdjustmentService::class);
        $stockService->adjust($product, $warehouse, 10, 'Initial Stock', $this->user, 'opening');

        // Simulate 2 parallel decrements of 8 units when starting balance is only 10 units.
        // First decrement must succeed; second decrement must fail due to insufficient stock.
        $firstSucceeded = false;
        $secondFailed = false;

        try {
            DB::transaction(function () use ($stockService, $product, $warehouse, &$firstSucceeded) {
                $stockService->adjust($product, $warehouse, -8, 'Attempt 1', $this->user, 'adjustment_out');
                $firstSucceeded = true;
            });
        } catch (\Throwable $e) {
            $firstSucceeded = false;
        }

        try {
            DB::transaction(function () use ($stockService, $product, $warehouse) {
                $stockService->adjust($product, $warehouse, -8, 'Attempt 2', $this->user, 'adjustment_out');
            });
        } catch (\RuntimeException $e) {
            // StockMovementService throws 'Insufficient stock' or 'negative inventory'
            $secondFailed = str_contains($e->getMessage(), 'Insufficient stock')
                || str_contains($e->getMessage(), 'negative inventory');
        }

        $this->assertTrue($firstSucceeded);
        $this->assertTrue($secondFailed);

        $finalStock = WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->first();
        $this->assertEquals(2.0, (float) $finalStock->quantity);
    }
}
