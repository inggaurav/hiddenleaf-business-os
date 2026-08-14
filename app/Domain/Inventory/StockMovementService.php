<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockMovementService
{
    /**
     * Atomically records an inventory movement and updates the cached warehouse stock.
     */
    public function recordMovement(
        int $organizationId,
        int $workspaceId,
        int $warehouseId,
        int $productId,
        string $movementType,
        float $quantity,
        int $direction, // +1 for stock in, -1 for stock out
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $unitCost = null,
        ?string $reason = null,
        ?string $notes = null,
        ?User $actor = null,
        ?int $sourceWarehouseId = null,
        ?int $destinationWarehouseId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Movement quantity must be greater than zero.');
        }

        if (! in_array($direction, [1, -1], true)) {
            throw new InvalidArgumentException('Direction must be 1 (in) or -1 (out).');
        }

        return DB::transaction(function () use (
            $organizationId,
            $workspaceId,
            $warehouseId,
            $productId,
            $movementType,
            $quantity,
            $direction,
            $referenceType,
            $referenceId,
            $unitCost,
            $reason,
            $notes,
            $actor,
            $sourceWarehouseId,
            $destinationWarehouseId
        ) {
            // Ensure product is a stockable item and belongs to workspace
            $product = ProductServiceItem::forTenant($organizationId, $workspaceId)
                ->lockForUpdate()
                ->findOrFail($productId);

            if ($product->type === 'service') {
                throw new InvalidArgumentException("Cannot record inventory movements for non-stock service item {$product->name}.");
            }

            // Ensure warehouse belongs to workspace
            $warehouse = Warehouse::where('organization_id', $organizationId)
                ->where('workspace_id', $workspaceId)
                ->findOrFail($warehouseId);

            // Lock or create warehouse stock record
            $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = WarehouseStock::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => 0,
                ]);
                $currentQty = 0.0;
            } else {
                $currentQty = (float) $stock->quantity;
            }

            $qtyChange = $quantity * $direction;
            $newQty = round($currentQty + $qtyChange, 4);

            if ($newQty < 0 && $direction === -1) {
                throw new InvalidArgumentException("Insufficient stock in warehouse {$warehouse->name} for {$product->name}. Requested {$quantity}, available {$currentQty}.");
            }

            $stock->update(['quantity' => $newQty]);

            $cost = $unitCost ?? (float) ($product->purchase_price ?: $product->sale_price ?: 0);
            $totalCost = round($cost * $quantity, 2);

            return StockMovement::create([
                'organization_id' => $organizationId,
                'workspace_id' => $workspaceId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'type' => $movementType,
                'quantity' => $quantity,
                'direction' => $direction,
                'balance_after' => $newQty,
                'unit_cost' => $cost,
                'total_cost' => $totalCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $destinationWarehouseId,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);
        });
    }
}
