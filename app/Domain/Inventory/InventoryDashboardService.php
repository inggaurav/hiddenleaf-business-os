<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\ProductServiceUnit;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    public function getMetrics(Workspace $workspace): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        $totalProducts = ProductServiceItem::forTenant($orgId, $wsId)->where('type', 'product')->count();
        $totalServices = ProductServiceItem::forTenant($orgId, $wsId)->where('type', 'service')->count();
        $totalCategories = ProductServiceCategory::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
        $totalUnits = ProductServiceUnit::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
        $totalWarehouses = Warehouse::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();

        $warehouses = Warehouse::where('organization_id', $orgId)->where('workspace_id', $wsId)->get();
        $warehouseIds = $warehouses->pluck('id');

        $totalStockUnits = (float) WarehouseStock::whereIn('warehouse_id', $warehouseIds)->sum('quantity');
        $lowStockItems = WarehouseStock::whereIn('warehouse_id', $warehouseIds)->where('quantity', '>', 0)->where('quantity', '<=', 5)->count();
        $outOfStockItems = WarehouseStock::whereIn('warehouse_id', $warehouseIds)->where('quantity', '<=', 0)->count();

        $totalTransfers = Transfer::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();

        // Warehouse Stock Distribution
        $warehouseDistribution = [];
        foreach ($warehouses as $wh) {
            $stockSum = (float) WarehouseStock::where('warehouse_id', $wh->id)->sum('quantity');
            $warehouseDistribution[] = [
                'warehouse_id' => $wh->id,
                'name' => $wh->name,
                'stock_units' => $stockSum,
            ];
        }

        // Recent stock movements
        $recentMovements = StockMovement::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->with(['product', 'warehouse'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'product_name' => $m->product->name ?? 'Product',
                'warehouse_name' => $m->warehouse->name ?? 'Warehouse',
                'quantity' => (float) $m->quantity,
                'type' => $m->type,
                'reason' => $m->reason ?? 'Stock movement',
                'created_at' => $m->created_at->format('M d, Y H:i'),
            ]);

        // Recent transfers
        $recentTransfers = Transfer::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->with(['fromWarehouse', 'toWarehouse', 'product'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'transfer_number' => $t->transfer_number ?? 'TRF-' . $t->id,
                'from_warehouse' => $t->fromWarehouse->name ?? 'Warehouse',
                'to_warehouse' => $t->toWarehouse->name ?? 'Warehouse',
                'product_name' => $t->product->name ?? 'Item',
                'quantity' => (float) $t->quantity,
                'date' => $t->date,
                'status' => $t->status ?? 'completed',
            ]);

        return [
            'stats' => [
                'total_products' => $totalProducts,
                'total_services' => $totalServices,
                'total_categories' => $totalCategories,
                'total_units' => $totalUnits,
                'total_warehouses' => $totalWarehouses,
                'total_stock_units' => $totalStockUnits,
                'low_stock_items' => $lowStockItems,
                'out_of_stock_items' => $outOfStockItems,
                'total_transfers' => $totalTransfers,
            ],
            'warehouseDistribution' => $warehouseDistribution,
            'recentMovements' => $recentMovements,
            'recentTransfers' => $recentTransfers,
        ];
    }
}
