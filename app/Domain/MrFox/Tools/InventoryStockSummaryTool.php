<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\ProductServiceItem;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

class InventoryStockSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'inventory.stock.summary';
    }

    public function description(): string
    {
        return 'Get total catalog product count, warehouse inventory counts, total units in stock, and total valuation.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'inventory.manage';
    }

    public function requiredModule(): ?string
    {
        return 'productservice';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();

        $products = ProductServiceItem::where('workspace_id', $wsId)->where('type', 'product')->get();
        $warehouses = Warehouse::where('workspace_id', $wsId)->count();
        $stocks = WarehouseStock::whereIn('warehouse_id', Warehouse::where('workspace_id', $wsId)->pluck('id'))->get();
        $totalUnits = (float) $stocks->sum('quantity');

        $data = [
            'total_sku_count' => $products->count(),
            'total_units_on_hand' => $totalUnits,
            'warehouses_count' => $warehouses,
        ];

        $summary = sprintf(
            'Inventory Summary: %d SKUs | %d total units across %d warehouse(s)',
            $products->count(),
            $totalUnits,
            $warehouses
        );

        return ToolResult::success($data, $summary, [
            ['type' => 'inventory', 'label' => 'Product Catalog', 'route' => '/products'],
            ['type' => 'inventory', 'label' => 'Warehouse Management', 'route' => '/warehouses'],
        ]);
    }
}
