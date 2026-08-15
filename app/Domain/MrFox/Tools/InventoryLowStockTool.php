<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\ProductServiceItem;

class InventoryLowStockTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'inventory.low_stock';
    }

    public function description(): string
    {
        return 'Find all products that have fallen below safety stock threshold or require urgent purchase reorder.';
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
        $orgId = $context->getOrganizationId();
        $wsId = $context->getWorkspaceId();

        $lowStock = ProductServiceItem::forTenant($orgId, $wsId)
            ->where('type', 'product')
            ->where('reorder_level', '>', 0)
            ->take(50)
            ->get();

        $safeData = $lowStock->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'sale_price' => (float) $p->sale_price,
            'purchase_price' => (float) $p->purchase_price,
            'reorder_level' => (float) $p->reorder_level,
        ]);

        $evidence = $lowStock->map(fn ($p) => [
            'type' => 'product',
            'id' => $p->id,
            'label' => "{$p->name} ({$p->sku}) - Reorder Level: {$p->reorder_level}",
            'route' => '/products',
        ])->all();

        $summary = sprintf('Identified %d product(s) with configured reorder thresholds.', $lowStock->count());

        return ToolResult::success($safeData->toArray(), $summary, $evidence);
    }
}
