<?php

namespace Tests\Feature\POS;

use App\Domain\POS\PosCheckoutService;
use App\Domain\POS\PosReturnService;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosRefundLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_return_restores_stock_and_posts_balanced_refund_journal_once(): void
    {
        $this->seed();

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create([
            'name' => 'POS Refund Ledger',
            'status' => true,
            'modules' => ['pos', 'productservice', 'account'],
            'created_by' => $user->id,
        ]);
        $organization = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'created_by' => $user->id]);
        $organization->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);

        $warehouse = Warehouse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Refund Warehouse',
            'created_by' => $user->id,
        ]);
        $counter = BillingCounter::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Refund Counter',
            'counter_number' => 'REFUND-1',
            'created_by' => $user->id,
        ]);
        $product = ProductServiceItem::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Refund Product',
            'sku' => 'REFUND-PRODUCT-1',
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

        $sale = app(PosCheckoutService::class)->checkout($workspace->id, $organization->id, $user, [
            'billing_counter_id' => $counter->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cash',
            'idempotency_key' => 'refund-ledger-sale',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2.0000',
            ]],
        ])->load('items');

        $this->assertSame('3.0000', WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->firstOrFail()->quantity);
        $saleItem = $sale->items->sole();

        $returns = app(PosReturnService::class);
        $return = $returns->createReturn($sale, $user, [[
            'pos_sale_item_id' => $saleItem->id,
            'product_id' => $product->id,
            'quantity' => '1.0000',
        ]], 'Customer return', 'cash', 'CASH-REFUND-1');
        $return = $returns->approve($return, $user);
        $return = $returns->complete($return, $user);

        $this->assertSame('completed', $return->status);
        $this->assertNotNull($return->journal_entry_id);
        $this->assertSame('4.0000', WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->firstOrFail()->quantity);

        $entry = JournalEntry::with('lines')->findOrFail($return->journal_entry_id);
        $this->assertSame('posted', $entry->status);
        $this->assertSame('POS-RETURN-'.$return->return_number, $entry->reference);
        $this->assertSame(
            number_format((float) $entry->lines->sum('debit'), 2, '.', ''),
            number_format((float) $entry->lines->sum('credit'), 2, '.', '')
        );
        $this->assertSame('100.00', number_format((float) $entry->lines->sum('debit'), 2, '.', ''));

        // Completing the same return again is idempotent: no second journal or stock movement.
        $again = $returns->complete($return->fresh(), $user);
        $this->assertSame($entry->id, $again->journal_entry_id);
        $this->assertSame(1, JournalEntry::where('workspace_id', $workspace->id)->where('reference', 'POS-RETURN-'.$return->return_number)->count());
        $this->assertSame('4.0000', WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->firstOrFail()->quantity);
    }
}
