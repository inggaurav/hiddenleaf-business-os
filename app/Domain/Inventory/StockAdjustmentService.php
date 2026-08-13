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
            $stock = WarehouseStock::query()->where(['product_id' => $product->id, 'warehouse_id' => $warehouse->id])->lockForUpdate()->first();
            $current = (float) ($stock?->quantity ?? 0);
            $balance = round($current + $quantity, 2);
            if ($balance < 0) {
                throw new RuntimeException('Stock adjustment would create negative inventory.');
            }

            $stock = WarehouseStock::updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['quantity' => $balance],
            );
            $movement = StockMovement::create([
                'organization_id' => $product->organization_id,
                'workspace_id' => $product->workspace_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'reason' => $reason,
                'created_by' => $actor->id,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
            $this->audit->log($actor->id, $product->organization_id, $product->workspace_id, 'inventory.'.$type, 'stock_movement', (string) $movement->id, [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'reason' => $reason,
            ], critical: true);

            return $movement;
        });
    }
}
