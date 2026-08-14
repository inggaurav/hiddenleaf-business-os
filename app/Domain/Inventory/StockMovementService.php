<?php

namespace App\Domain\Inventory;

use App\Domain\Accounting\Money;
use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canonical inventory mutation engine.
 *
 * Every operational stock change must pass through this service. The product
 * row lock serializes first-stock-row creation and movements for a product,
 * while the warehouse/product unique constraint protects materialized stock at
 * the database layer. Historical StockMovement rows are the audit source of
 * truth; WarehouseStock is a materialized balance.
 */
class StockMovementService
{
    public function recordMovement(
        int $organizationId,
        int $workspaceId,
        int $warehouseId,
        int $productId,
        string $movementType,
        InventoryQuantity|string|int|float $quantity,
        int $direction,
        ?string $referenceType = null,
        ?int $referenceId = null,
        Money|string|int|float|null $unitCost = null,
        ?string $reason = null,
        ?string $notes = null,
        ?User $actor = null,
        ?int $sourceWarehouseId = null,
        ?int $destinationWarehouseId = null,
        ?int $referenceLineId = null,
    ): StockMovement {
        $qty = InventoryQuantity::of($quantity);
        if (! $qty->isPositive()) {
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
            $qty,
            $direction,
            $referenceType,
            $referenceId,
            $unitCost,
            $reason,
            $notes,
            $actor,
            $sourceWarehouseId,
            $destinationWarehouseId,
            $referenceLineId,
        ) {
            $product = ProductServiceItem::forTenant($organizationId, $workspaceId)
                ->lockForUpdate()
                ->findOrFail($productId);

            if ($product->type !== 'product') {
                throw new InvalidArgumentException("Cannot record inventory movements for non-stock service item {$product->name}.");
            }

            $warehouse = Warehouse::query()
                ->where('organization_id', $organizationId)
                ->where('workspace_id', $workspaceId)
                ->findOrFail($warehouseId);

            // Generated business movements are idempotent at the service layer;
            // the matching DB unique constraint remains the final authority.
            if ($referenceType !== null && $referenceId !== null && $referenceLineId !== null) {
                $existing = StockMovement::query()
                    ->where('organization_id', $organizationId)
                    ->where('workspace_id', $workspaceId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->where('type', $movementType)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('reference_line_id', $referenceLineId)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            // The product lock above serializes the no-row case; the unique
            // warehouse/product constraint prevents any duplicate materialized
            // stock row even if another code path is introduced later.
            $stock = WarehouseStock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = WarehouseStock::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => InventoryQuantity::zero()->toStorageString(),
                ]);
            }

            $current = InventoryQuantity::of((string) $stock->quantity);
            $new = $direction === -1 ? $current->subtract($qty) : $current->add($qty);

            if ($new->isNegative()) {
                throw new RuntimeException(
                    "Insufficient stock in warehouse {$warehouse->name} for {$product->name}. Requested {$qty}, available {$current}."
                );
            }

            $stock->update(['quantity' => $new->toStorageString()]);

            $cost = $unitCost instanceof Money
                ? $unitCost
                : Money::of((string) ($unitCost ?? $product->purchase_price ?? $product->sale_price ?? '0'), 4);
            $totalCost = $cost->multiplyByDecimal($qty->toStorageString());

            return StockMovement::create([
                'organization_id' => $organizationId,
                'workspace_id' => $workspaceId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'type' => $movementType,
                'quantity' => $qty->toStorageString(),
                'direction' => $direction,
                'balance_after' => $new->toStorageString(),
                'unit_cost' => $cost->toStorageString(),
                'total_cost' => $totalCost->toStorageString(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_line_id' => $referenceLineId,
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $destinationWarehouseId,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);
        });
    }
}
