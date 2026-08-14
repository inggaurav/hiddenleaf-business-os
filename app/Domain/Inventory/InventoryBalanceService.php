<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class InventoryBalanceService
{
    public function getCalculatedQuantity(int $organizationId, int $workspaceId, int $warehouseId, int $productId): InventoryQuantity
    {
        $sum = StockMovement::forWorkspace($organizationId, $workspaceId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity * direction), 0) as total')
            ->value('total');

        return InventoryQuantity::of((string) ($sum ?? '0'));
    }

    /**
     * Compatibility accessor; returns an exact decimal string, never float.
     */
    public function getCalculatedStock(int $organizationId, int $workspaceId, int $warehouseId, int $productId): string
    {
        return $this->getCalculatedQuantity($organizationId, $workspaceId, $warehouseId, $productId)->toStorageString();
    }

    /**
     * Audit and optionally repair materialized WarehouseStock from the immutable
     * StockMovement ledger. Repair changes only the cache and records an audit
     * event; it never fabricates a movement to make the ledger fit the cache.
     *
     * @return array<int, array<string, int|string>>
     */
    public function reconcileWarehouseStocks(int $organizationId, int $workspaceId, bool $repair = false): array
    {
        $rows = [];
        $warehouses = Warehouse::where('organization_id', $organizationId)
            ->where('workspace_id', $workspaceId)
            ->get();
        $products = ProductServiceItem::forTenant($organizationId, $workspaceId)
            ->where('type', 'product')
            ->get();

        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                $stock = WarehouseStock::where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->first();

                $stored = InventoryQuantity::of((string) ($stock?->quantity ?? '0'));
                $calculated = $this->getCalculatedQuantity($organizationId, $workspaceId, $warehouse->id, $product->id);
                $difference = $stored->subtract($calculated);
                $status = $difference->isZero() ? 'MATCH' : 'DISCREPANCY';

                if ($status === 'DISCREPANCY' && $repair) {
                    DB::transaction(function () use ($warehouse, $product, $calculated, $stored, $difference, &$stock) {
                        // Product lock serializes repair with first-row stock
                        // creation and normal movement operations.
                        ProductServiceItem::forTenant($warehouse->organization_id, $warehouse->workspace_id)
                            ->whereKey($product->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $stock = WarehouseStock::where('warehouse_id', $warehouse->id)
                            ->where('product_id', $product->id)
                            ->lockForUpdate()
                            ->first();

                        if ($stock) {
                            $stock->update(['quantity' => $calculated->toStorageString()]);
                        } else {
                            $stock = WarehouseStock::create([
                                'warehouse_id' => $warehouse->id,
                                'product_id' => $product->id,
                                'quantity' => $calculated->toStorageString(),
                            ]);
                        }

                        app(AuditLogger::class)->log(
                            null,
                            $warehouse->organization_id,
                            $warehouse->workspace_id,
                            'inventory.reconcile_repair',
                            'warehouse_stock',
                            (string) $stock->id,
                            [
                                'previous_quantity' => $stored->toStorageString(),
                                'new_quantity' => $calculated->toStorageString(),
                                'difference' => $difference->toStorageString(),
                            ],
                            critical: true,
                        );
                    });
                    $status = 'REPAIRED';
                }

                $rows[] = [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'stored_quantity' => $stored->toStorageString(),
                    'calculated_quantity' => $calculated->toStorageString(),
                    'difference' => $difference->toStorageString(),
                    'status' => $status,
                ];
            }
        }

        return $rows;
    }
}
