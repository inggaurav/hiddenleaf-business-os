<?php

namespace Tests\Feature\ProductService;

use App\Domain\Inventory\StockAdjustmentService;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProductServiceItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
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

    public function test_multi_warehouse_transfer_lifecycle(): void
    {
        $whSource = Warehouse::create([
            'name' => 'Origin Warehouse A',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $whDest = Warehouse::create([
            'name' => 'Target Warehouse B',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Fiber Optic Cable',
            'sku' => 'FBR-001',
            'type' => 'product',
            'sale_price' => 50.00,
            'purchase_price' => 20.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        // Seed initial stock in origin warehouse
        app(StockAdjustmentService::class)->adjust($product, $whSource, 100, 'Opening balance', $this->user, 'opening');

        // Execute transfer via HTTP endpoint
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('transfers.store'), [
                'from_warehouse' => $whSource->id,
                'to_warehouse' => $whDest->id,
                'product_id' => $product->id,
                'quantity' => 35,
                'date' => now()->toDateString(),
            ]);

        $response->assertRedirect(route('transfers.index'));

        // Check transfer record
        $transfer = Transfer::where('product_id', $product->id)->firstOrFail();
        $this->assertEquals(35.0, (float) $transfer->quantity);
        $this->assertEquals('completed', $transfer->status);
        $this->assertNotNull($transfer->transfer_number);

        // Check balances in both warehouses
        $sourceStock = WarehouseStock::where('warehouse_id', $whSource->id)->where('product_id', $product->id)->first();
        $destStock = WarehouseStock::where('warehouse_id', $whDest->id)->where('product_id', $product->id)->first();

        $this->assertEquals(65.0, (float) $sourceStock->quantity);
        $this->assertEquals(35.0, (float) $destStock->quantity);

        // Check movements ledger
        $movements = StockMovement::where('product_id', $product->id)->get();
        $this->assertCount(3, $movements); // 1 opening, 1 transfer_out, 1 transfer_in

        $transferOut = $movements->where('type', 'transfer_out')->first();
        $transferIn = $movements->where('type', 'transfer_in')->first();

        $this->assertNotNull($transferOut);
        $this->assertEquals(-1, $transferOut->direction);
        $this->assertEquals(35.0, (float) $transferOut->quantity);
        $this->assertEquals(65.0, (float) $transferOut->balance_after);

        $this->assertNotNull($transferIn);
        $this->assertEquals(1, $transferIn->direction);
        $this->assertEquals(35.0, (float) $transferIn->quantity);
        $this->assertEquals(35.0, (float) $transferIn->balance_after);
    }

    public function test_transfer_cannot_exceed_available_source_stock(): void
    {
        $whSource = Warehouse::create([
            'name' => 'Low Stock Warehouse',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $whDest = Warehouse::create([
            'name' => 'Destination Warehouse',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Rare Chip',
            'sku' => 'CHP-001',
            'type' => 'product',
            'sale_price' => 500.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        // Seed only 5 units
        app(StockAdjustmentService::class)->adjust($product, $whSource, 5, 'Opening', $this->user, 'opening');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock adjustment would create negative inventory');

        app(\App\Domain\Inventory\InventoryTransferService::class)->transfer(
            $whSource,
            $whDest,
            $product,
            10,
            now()->toDateString(),
            $this->user
        );
    }

    public function test_completed_transfer_is_immutable(): void
    {
        $whSource = Warehouse::create([
            'name' => 'Source',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $whDest = Warehouse::create([
            'name' => 'Dest',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Widget',
            'sku' => 'WDG-001',
            'type' => 'product',
            'sale_price' => 10.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $transfer = Transfer::create([
            'from_warehouse' => $whSource->id,
            'to_warehouse' => $whDest->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'date' => now()->toDateString(),
            'status' => 'completed',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->delete(route('transfers.destroy', $transfer));

        $response->assertStatus(422);
    }
}
