<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockAdjustmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function adjust(
        ProductServiceItem $product,
        Warehouse $warehouse,
        float $quantity,
        string $reason,
        User $actor,
        string $type = 'adjusted',
        ?Model $reference = null,
    ): StockMovement {
        if ($product->type !== 'product') {
            throw new RuntimeException('Services cannot hold warehouse inventory.');
        }
        if ((int) $product->workspace_id !== (int) $warehouse->workspace_id || (int) $product->organization_id !== (int) $warehouse->organization_id) {
            throw new RuntimeException('Product and warehouse must belong to the same tenant.');
        }
        if ($quantity == 0.0) {
            throw new RuntimeException('Stock adjustment quantity cannot be zero.');
        }

        return DB::transaction(function () use ($product, $warehouse, $quantity, $reason, $actor, $type, $reference) {
            $movementService = app(StockMovementService::class);
            
            $direction = $quantity >= 0 ? 1 : -1;
            $absQty = abs($quantity);
            $unitCost = (float) ($product->purchase_price ?: $product->sale_price ?: 0);
            
            $movement = $movementService->recordMovement(
                organizationId: $product->organization_id,
                workspaceId: $product->workspace_id,
                warehouseId: $warehouse->id,
                productId: $product->id,
                movementType: $type,
                quantity: $absQty,
                direction: $direction,
                referenceType: $reference?->getMorphClass(),
                referenceId: $reference?->getKey(),
                unitCost: $unitCost,
                reason: $reason,
                notes: $reason,
                actor: $actor
            );

            $this->audit->log($actor->id, $product->organization_id, $product->workspace_id, 'inventory.'.$type, 'stock_movement', (string) $movement->id, [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $quantity,
                'direction' => $direction,
                'balance_after' => $movement->balance_after,
                'reason' => $reason,
            ], critical: true);

            return $movement;
        });
    }
}
