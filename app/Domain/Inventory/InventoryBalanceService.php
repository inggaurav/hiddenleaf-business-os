<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;

class InventoryBalanceService
{
    /**
     * Calculates authoritative stock for a product in a warehouse from the historical stock movement ledger.
     */
    public function getCalculatedStock(int $organizationId, int $workspaceId, int $warehouseId, int $productId): float
    {
        $sum = StockMovement::forWorkspace($organizationId, $workspaceId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity * direction), 0) as total')
            ->value('total');

        return round((float) $sum, 4);
    }

    /**
     * Audits and optionally repairs stored warehouse stock records against the movement ledger.
     *
     * @return array<int, array{warehouse_id: int, warehouse_name: string, product_id: int, product_name: string, stored_quantity: float, calculated_quantity: float, difference: float, status: string}>
     */
    public function reconcileWarehouseStocks(int $organizationId, int $workspaceId, bool $repair = false): array
    {
        $discrepancies = [];

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

                $storedQty = $stock ? (float) $stock->quantity : 0.0;
                $calculatedQty = $this->getCalculatedStock($organizationId, $workspaceId, $warehouse->id, $product->id);
                $diff = round($storedQty - $calculatedQty, 4);

                $status = abs($diff) < 0.0001 ? 'MATCH' : 'DISCREPANCY';

                if ($status === 'DISCREPANCY' && $repair) {
                    DB::transaction(function () use ($warehouse, $product, $calculatedQty, &$stock) {
                        if ($stock) {
                            $stock->update(['quantity' => $calculatedQty]);
                        } else {
                            $stock = WarehouseStock::create([
                                'warehouse_id' => $warehouse->id,
                                'product_id' => $product->id,
                                'quantity' => $calculatedQty,
                            ]);
                        }
                    });
                    $status = 'REPAIRED';
                }

                $discrepancies[] = [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'stored_quantity' => $storedQty,
                    'calculated_quantity' => $calculatedQty,
                    'difference' => $diff,
                    'status' => $status,
                ];
            }
        }

        return $discrepancies;
    }
}
