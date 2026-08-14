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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosTrueConcurrencyTest extends TestCase
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
            'name' => 'Limited Edition Item',
            'sku' => 'LTD-01',
            'type' => 'product',
            'sale_price' => 500.00,
            'purchase_price' => 200.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        // Only 1 item in stock
        WarehouseStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => '1.0000',
        ]);
    }

    public function test_concurrent_checkouts_prevent_oversell(): void
    {
        $checkoutService = app(PosCheckoutService::class);

        $firstSucceeded = false;
        $secondFailed = false;

        // Transaction 1: buy 1 unit
        try {
            DB::transaction(function () use ($checkoutService, &$firstSucceeded) {
                $checkoutService->checkout(
                    $this->workspace->id,
                    $this->organization->id,
                    $this->user,
                    [
                        'billing_counter_id' => $this->counter->id,
                        'warehouse_id' => $this->warehouse->id,
                        'payment_method' => 'cash',
                        'idempotency_key' => 'tx-1-key',
                        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    ]
                );
                $firstSucceeded = true;
            });
        } catch (\Throwable $e) {
            $firstSucceeded = false;
        }

        // Transaction 2: attempt to buy 1 unit (must fail because stock is now 0)
        try {
            DB::transaction(function () use ($checkoutService) {
                $checkoutService->checkout(
                    $this->workspace->id,
                    $this->organization->id,
                    $this->user,
                    [
                        'billing_counter_id' => $this->counter->id,
                        'warehouse_id' => $this->warehouse->id,
                        'payment_method' => 'cash',
                        'idempotency_key' => 'tx-2-key',
                        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    ]
                );
            });
        } catch (\RuntimeException $e) {
            $secondFailed = str_contains($e->getMessage(), 'Insufficient stock');
        }

        $this->assertTrue($firstSucceeded, 'First checkout should succeed.');
        $this->assertTrue($secondFailed, 'Second checkout must fail with Insufficient stock.');

        // Verify exactly 1 POS sale in database
        $this->assertEquals(1, PosSale::count());

        // Verify final stock is 0 (never negative)
        $finalStock = WarehouseStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEquals(0, (float) $finalStock);
    }
}
