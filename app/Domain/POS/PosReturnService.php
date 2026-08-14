<?php

namespace App\Domain\POS;

use App\Domain\Inventory\StockMovementService;
use App\Models\POS\PosReturn;
use App\Models\POS\PosReturnItem;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosReturnService
{
    public function __construct(
        private readonly StockMovementService $stockMovement,
        private readonly ReturnNumberService $returnNumber,
    ) {}

    /**
     * Create a draft return request.
     */
    public function createReturn(PosSale $sale, User $actor, array $items, string $reason, string $refundMethod = 'cash'): PosReturn
    {
        return DB::transaction(function () use ($sale, $actor, $items, $reason, $refundMethod) {
            $this->validateReturnItems($sale, $items);

            $refundAmount = '0.0000';
            foreach ($items as $item) {
                $saleItem = PosSaleItem::find($item['pos_sale_item_id']);
                $lineUnit = bcdiv((string) $saleItem->line_total, (string) $saleItem->quantity, 4);
                $refundAmount = bcadd($refundAmount, bcmul($lineUnit, (string) $item['quantity'], 4), 4);
            }

            $returnNumber = $this->returnNumber->next($sale->workspace_id);

            $return = PosReturn::create([
                'organization_id' => $sale->organization_id,
                'workspace_id' => $sale->workspace_id,
                'pos_sale_id' => $sale->id,
                'return_number' => $returnNumber,
                'status' => 'draft',
                'reason' => $reason,
                'refund_amount' => $refundAmount,
                'refund_method' => $refundMethod,
                'created_by' => $actor->id,
            ]);

            foreach ($items as $item) {
                PosReturnItem::create([
                    'pos_return_id' => $return->id,
                    'pos_sale_item_id' => $item['pos_sale_item_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'refund_amount' => '0.0000',
                ]);
            }

            return $return;
        });
    }

    /**
     * Approve a draft return (no stock movement yet).
     */
    public function approve(PosReturn $return, User $actor): PosReturn
    {
        if ($return->status !== 'draft') {
            throw new RuntimeException("Only draft returns can be approved. Current status: {$return->status}");
        }

        $return->update([
            'status' => 'approved',
            'processed_by' => $actor->id,
        ]);

        return $return->fresh();
    }

    /**
     * Complete an approved return — idempotent stock restoration.
     */
    public function complete(PosReturn $return, User $actor): PosReturn
    {
        if ($return->status === 'completed') {
            // Idempotent — already done
            return $return;
        }

        if ($return->status !== 'approved') {
            throw new RuntimeException("Only approved returns can be completed. Current status: {$return->status}");
        }

        return DB::transaction(function () use ($return, $actor) {
            $sale = $return->sale;
            $warehouse = Warehouse::findOrFail($sale->warehouse_id);

            foreach ($return->items as $returnItem) {
                $saleItem = $returnItem->saleItem;

                // Only restore stock for physical products
                if ($saleItem->type === 'product') {
                    $this->stockMovement->recordMovement(
                        organizationId: $sale->organization_id,
                        workspaceId: $sale->workspace_id,
                        warehouseId: $warehouse->id,
                        productId: $returnItem->product_id,
                        movementType: 'pos_return',
                        quantity: (float) $returnItem->quantity,
                        direction: 1,
                        referenceType: 'pos_return',
                        referenceId: $return->id,
                        unitCost: (float) $saleItem->unit_price,
                        reason: "POS Return {$return->return_number}",
                    );
                }
            }

            $return->update([
                'status' => 'completed',
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ]);

            return $return->fresh();
        });
    }

    /**
     * Cancel a draft or approved return (no stock impact).
     */
    public function cancel(PosReturn $return, User $actor): PosReturn
    {
        if (in_array($return->status, ['completed', 'cancelled'])) {
            throw new RuntimeException("Cannot cancel a {$return->status} return.");
        }

        $return->update(['status' => 'cancelled']);
        return $return->fresh();
    }

    private function validateReturnItems(PosSale $sale, array $items): void
    {
        foreach ($items as $item) {
            $saleItem = PosSaleItem::where('pos_sale_id', $sale->id)
                ->where('id', $item['pos_sale_item_id'])
                ->firstOrFail();

            // Calculate already-returned quantity
            $alreadyReturned = PosReturnItem::whereHas('return', function ($q) use ($sale) {
                $q->where('pos_sale_id', $sale->id)
                  ->whereIn('status', ['approved', 'completed']);
            })->where('pos_sale_item_id', $saleItem->id)
              ->sum('quantity');

            $available = bcsub((string) $saleItem->quantity, (string) $alreadyReturned, 4);

            if (bccomp((string) $item['quantity'], $available, 4) > 0) {
                throw new RuntimeException(
                    "Return quantity {$item['quantity']} for '{$saleItem->product_name}' exceeds available {$available} (sold: {$saleItem->quantity}, already returned: {$alreadyReturned})."
                );
            }
        }
    }
}