<?php

namespace App\Domain\POS;

use App\Domain\Accounting\Money;
use App\Domain\Inventory\InventoryQuantity;
use App\Domain\Inventory\StockMovementService;
use App\Models\POS\PosReturn;
use App\Models\POS\PosReturnItem;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosReturnService
{
    public function __construct(
        private readonly StockMovementService $stockMovement,
        private readonly ReturnNumberService $returnNumber,
        private readonly PosAccountingService $accounting,
    ) {}

    public function createReturn(PosSale $sale, User $actor, array $items, string $reason, string $refundMethod = 'cash', ?string $refundReference = null): PosReturn
    {
        return DB::transaction(function () use ($sale, $actor, $items, $reason, $refundMethod, $refundReference) {
            $lockedSale = PosSale::query()
                ->where('organization_id', $sale->organization_id)
                ->where('workspace_id', $sale->workspace_id)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateReturnItems($lockedSale, $items);

            $refundAmount = Money::zero(4);
            $prepared = [];
            foreach ($items as $item) {
                $saleItem = PosSaleItem::where('pos_sale_id', $lockedSale->id)
                    ->whereKey($item['pos_sale_item_id'])
                    ->firstOrFail();
                $qty = InventoryQuantity::of((string) $item['quantity']);
                $ratio = bcdiv($qty->toStorageString(), (string) $saleItem->quantity, 8);
                $lineRefund = Money::of((string) $saleItem->line_total, 4)->multiplyByDecimal($ratio);
                $refundAmount = $refundAmount->add($lineRefund);
                $prepared[] = [$saleItem, $qty, $lineRefund];
            }

            $return = PosReturn::create([
                'organization_id' => $lockedSale->organization_id,
                'workspace_id' => $lockedSale->workspace_id,
                'pos_sale_id' => $lockedSale->id,
                'return_number' => $this->returnNumber->next($lockedSale->workspace_id),
                'status' => 'draft',
                'reason' => $reason,
                'refund_amount' => $refundAmount->toStorageString(),
                'refund_method' => $refundMethod,
                'refund_reference' => $refundReference,
                'created_by' => $actor->id,
            ]);

            foreach ($prepared as [$saleItem, $qty, $lineRefund]) {
                PosReturnItem::create([
                    'pos_return_id' => $return->id,
                    'pos_sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $qty->toStorageString(),
                    'refund_amount' => $lineRefund->toStorageString(),
                ]);
            }

            return $return->load('items');
        });
    }

    public function approve(PosReturn $return, User $actor): PosReturn
    {
        return DB::transaction(function () use ($return, $actor) {
            $locked = PosReturn::with(['sale', 'items'])->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw new RuntimeException("Only draft returns can be approved. Current status: {$locked->status}");
            }

            $this->validateReturnItems($locked->sale, $locked->items->map(fn (PosReturnItem $item) => [
                'pos_sale_item_id' => $item->pos_sale_item_id,
                'quantity' => (string) $item->quantity,
            ])->all(), $locked->id);

            $locked->update([
                'status' => 'approved',
                'processed_by' => $actor->id,
            ]);

            return $locked->fresh();
        });
    }

    public function complete(PosReturn $return, User $actor): PosReturn
    {
        return DB::transaction(function () use ($return, $actor) {
            $locked = PosReturn::with(['sale', 'items.saleItem'])->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'completed') {
                return $locked;
            }
            if ($locked->status !== 'approved') {
                throw new RuntimeException("Only approved returns can be completed. Current status: {$locked->status}");
            }

            $this->validateReturnItems($locked->sale, $locked->items->map(fn (PosReturnItem $item) => [
                'pos_sale_item_id' => $item->pos_sale_item_id,
                'quantity' => (string) $item->quantity,
            ])->all(), $locked->id);

            $warehouse = Warehouse::query()
                ->where('organization_id', $locked->organization_id)
                ->where('workspace_id', $locked->workspace_id)
                ->findOrFail($locked->sale->warehouse_id);

            foreach ($locked->items as $returnItem) {
                $saleItem = $returnItem->saleItem;
                if (! $saleItem || $saleItem->type !== 'product') {
                    continue;
                }

                $product = ProductServiceItem::query()
                    ->where('organization_id', $locked->organization_id)
                    ->where('workspace_id', $locked->workspace_id)
                    ->findOrFail($returnItem->product_id);

                $this->stockMovement->recordMovement(
                    organizationId: $locked->organization_id,
                    workspaceId: $locked->workspace_id,
                    warehouseId: $warehouse->id,
                    productId: $returnItem->product_id,
                    movementType: 'pos_return',
                    quantity: InventoryQuantity::of((string) $returnItem->quantity),
                    direction: 1,
                    referenceType: 'pos_return',
                    referenceId: $locked->id,
                    unitCost: Money::of((string) ($product->purchase_price ?? '0'), 4),
                    reason: "POS Return {$locked->return_number}",
                    actor: $actor,
                    referenceLineId: $returnItem->id,
                );
            }

            // Refund accounting and inventory restoration share the same outer
            // transaction. A ledger failure rolls the stock restoration back.
            $this->accounting->postReturn($locked, $actor);

            $locked->update([
                'status' => 'completed',
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function cancel(PosReturn $return, User $actor): PosReturn
    {
        return DB::transaction(function () use ($return) {
            $locked = PosReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['completed', 'cancelled'], true)) {
                throw new RuntimeException("Cannot cancel a {$locked->status} return.");
            }

            $locked->update(['status' => 'cancelled']);
            return $locked->fresh();
        });
    }

    private function validateReturnItems(PosSale $sale, array $items, ?int $excludeReturnId = null): void
    {
        foreach ($items as $item) {
            $saleItem = PosSaleItem::where('pos_sale_id', $sale->id)
                ->where('id', $item['pos_sale_item_id'])
                ->firstOrFail();

            $qty = InventoryQuantity::of((string) $item['quantity']);
            if (! $qty->isPositive()) {
                throw new RuntimeException('Return quantity must be greater than zero.');
            }

            $alreadyReturnedRaw = PosReturnItem::whereHas('return', function ($query) use ($sale, $excludeReturnId) {
                $query->where('pos_sale_id', $sale->id)
                    ->whereIn('status', ['approved', 'completed'])
                    ->when($excludeReturnId, fn ($q) => $q->where('id', '!=', $excludeReturnId));
            })->where('pos_sale_item_id', $saleItem->id)->sum('quantity');

            $sold = InventoryQuantity::of((string) $saleItem->quantity);
            $alreadyReturned = InventoryQuantity::of((string) $alreadyReturnedRaw);
            $available = $sold->subtract($alreadyReturned);

            if ($qty->greaterThan($available)) {
                throw new RuntimeException(
                    "Return quantity {$qty} for '{$saleItem->product_name}' exceeds available {$available} (sold: {$sold}, already returned: {$alreadyReturned})."
                );
            }
        }
    }
}
