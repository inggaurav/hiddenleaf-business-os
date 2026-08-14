<?php

namespace App\Domain\Inventory;

use App\Domain\Accounting\Money;
use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Business wrapper around the canonical StockMovementService.
 */
class StockAdjustmentService
{
    public function __construct(
        private readonly StockMovementService $movements,
        private readonly AuditLogger $audit,
    ) {}

    public function adjust(
        ProductServiceItem $product,
        Warehouse $warehouse,
        InventoryQuantity|string|int|float $quantity,
        string $reason,
        User $actor,
        string $type = 'adjusted',
        ?Model $reference = null,
        ?int $referenceLineId = null,
    ): StockMovement {
        if ($product->type !== 'product') {
            throw new RuntimeException('Services cannot hold warehouse inventory.');
        }
        if ((int) $product->workspace_id !== (int) $warehouse->workspace_id || (int) $product->organization_id !== (int) $warehouse->organization_id) {
            throw new RuntimeException('Product and warehouse must belong to the same tenant.');
        }

        $signed = InventoryQuantity::of($quantity);
        if ($signed->isZero()) {
            throw new RuntimeException('Stock adjustment quantity cannot be zero.');
        }

        $direction = $signed->isNegative() ? -1 : 1;
        $absolute = $signed->absolute();
        $unitCost = Money::of((string) ($product->purchase_price ?? $product->sale_price ?? '0'), 4);

        $movement = $this->movements->recordMovement(
            organizationId: $product->organization_id,
            workspaceId: $product->workspace_id,
            warehouseId: $warehouse->id,
            productId: $product->id,
            movementType: $type,
            quantity: $absolute,
            direction: $direction,
            referenceType: $reference?->getMorphClass(),
            referenceId: $reference?->getKey(),
            unitCost: $unitCost,
            reason: $reason,
            notes: $reason,
            actor: $actor,
            referenceLineId: $referenceLineId,
        );

        $this->audit->log($actor->id, $product->organization_id, $product->workspace_id, 'inventory.'.$type, 'stock_movement', (string) $movement->id, [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $signed->toStorageString(),
            'direction' => $direction,
            'balance_after' => $movement->balance_after,
            'reason' => $reason,
        ], critical: true);

        return $movement;
    }
}
