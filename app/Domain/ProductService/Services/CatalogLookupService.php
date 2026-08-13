<?php

namespace App\Domain\ProductService\Services;

use App\Models\ProductServiceItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class CatalogLookupService
{
    public function productsForWarehouse(int $warehouseId, int $organizationId, int $workspaceId): Collection
    {
        $warehouse = Warehouse::query()
            ->whereKey($warehouseId)
            ->where('organization_id', $organizationId)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        return ProductServiceItem::query()
            ->forTenant($organizationId, $workspaceId)
            ->where('type', 'product')
            ->where('is_active', true)
            ->whereHas('stocks', fn ($query) => $query
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity', '>', 0))
            ->with(['stocks' => fn ($query) => $query->where('warehouse_id', $warehouse->id)])
            ->orderBy('name')
            ->get()
            ->map(function (ProductServiceItem $item) {
                $item->setAttribute('quantity', $item->stocks->first()?->quantity ?? '0.00');
                $item->unsetRelation('stocks');

                return $item;
            });
    }

    public function services(int $organizationId, int $workspaceId): Collection
    {
        return ProductServiceItem::query()
            ->forTenant($organizationId, $workspaceId)
            ->where('type', 'service')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
