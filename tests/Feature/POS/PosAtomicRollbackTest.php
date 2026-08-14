<?php

namespace Tests\Feature\POS;

use App\Domain\POS\PosAccountingService;
use App\Domain\POS\PosCheckoutService;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosIdempotencyKey;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PosAtomicRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_failure_rolls_back_sale_items_stock_and_idempotency_state(): void
    {
        $this->seed();

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create([
            'name' => 'POS Atomic Rollback',
            'status' => true,
            'modules' => ['pos', 'productservice', 'account'],
            'created_by' => $user->id,
        ]);
        $organization = Organization::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        $workspace = Workspace::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
        ]);
        $organization->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);

        $warehouse = Warehouse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Atomic Warehouse',
            'created_by' => $user->id,
        ]);
        $counter = BillingCounter::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Atomic Counter',
            'counter_number' => 'ATOMIC-1',
            'created_by' => $user->id,
        ]);
        $product = ProductServiceItem::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Atomic Product',
            'sku' => 'ATOMIC-PRODUCT-1',
            'type' => 'product',
            'sale_price' => '100.00',
            'purchase_price' => '40.00',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '5.0000',
        ]);

        $accounting = Mockery::mock(PosAccountingService::class);
        $accounting->shouldReceive('postSale')
            ->once()
            ->andThrow(new RuntimeException('Synthetic ledger failure'));
        $this->app->instance(PosAccountingService::class, $accounting);

        $checkout = $this->app->make(PosCheckoutService::class);

        try {
            $checkout->checkout($workspace->id, $organization->id, $user, [
                'billing_counter_id' => $counter->id,
                'warehouse_id' => $warehouse->id,
                'payment_method' => 'cash',
                'idempotency_key' => 'atomic-ledger-failure-key',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => '2.0000',
                ]],
            ]);

            $this->fail('Checkout should fail when accounting posting fails.');
        } catch (RuntimeException $e) {
            $this->assertSame('Synthetic ledger failure', $e->getMessage());
        }

        $this->assertSame(0, PosSale::where('workspace_id', $workspace->id)->count());
        $this->assertSame(0, StockMovement::where('workspace_id', $workspace->id)->where('type', 'pos_sale')->count());
        $this->assertSame(0, PosIdempotencyKey::where('workspace_id', $workspace->id)->count());
        $this->assertSame(
            '5.0000',
            WarehouseStock::where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->firstOrFail()
                ->quantity
        );
    }
}
