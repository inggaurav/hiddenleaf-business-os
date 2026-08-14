<?php

namespace Tests\Feature\ProductService;

use App\Domain\Inventory\InventoryBalanceService;
use App\Domain\Inventory\StockAdjustmentService;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProductServiceItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReconciliationTest extends TestCase
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

    public function test_inventory_reconciliation_detects_and_repairs_discrepancy(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Recon Warehouse',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Recon Item',
            'sku' => 'REC-001',
            'type' => 'product',
            'sale_price' => 50.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        // Legitimate movement ledger: +50 units
        app(StockAdjustmentService::class)->adjust($product, $warehouse, 50, 'Opening Stock', $this->user, 'opening');

        $balancer = app(InventoryBalanceService::class);

        // 1. In sync
        $results = $balancer->reconcileWarehouseStocks($this->organization->id, $this->workspace->id);
        $itemResult = collect($results)->firstWhere('product_id', $product->id);
        $this->assertEquals('MATCH', $itemResult['status']);
        $this->assertEquals(50.0, (float) $itemResult['stored_quantity']);
        $this->assertEquals(50.0, (float) $itemResult['calculated_quantity']);

        // 2. Introduce artificial database corruption in warehouse_stocks table
        WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->update(['quantity' => 999.0]);

        $corruptResults = $balancer->reconcileWarehouseStocks($this->organization->id, $this->workspace->id);
        $corruptItem = collect($corruptResults)->firstWhere('product_id', $product->id);
        $this->assertEquals('DISCREPANCY', $corruptItem['status']);
        $this->assertEquals(999.0, (float) $corruptItem['stored_quantity']);
        $this->assertEquals(50.0, (float) $corruptItem['calculated_quantity']);

        // 3. Run reconciliation with repair = true
        $repairedResults = $balancer->reconcileWarehouseStocks($this->organization->id, $this->workspace->id, repair: true);
        $repairedItem = collect($repairedResults)->firstWhere('product_id', $product->id);
        $this->assertEquals('REPAIRED', $repairedItem['status']);

        // 4. Verify repaired state in database
        $freshStock = WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->first();
        $this->assertEquals(50.0, (float) $freshStock->quantity);

        // 5. Test CLI command execution
        $this->artisan('inventory:reconcile', ['--workspace' => $this->workspace->id])
            ->assertSuccessful();
    }
}
