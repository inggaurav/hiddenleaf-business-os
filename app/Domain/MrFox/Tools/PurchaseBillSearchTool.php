<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\PurchaseInvoice;

class PurchaseBillSearchTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'purchase.bill.search';
    }

    public function description(): string
    {
        return 'Search vendor bills and purchase invoices by number, vendor, status, or date.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional bill number search query',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by status: draft, posted, paid, cancelled',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum bills to return (default 10, max 50)',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'purchases.manage';
    }

    public function requiredModule(): ?string
    {
        return 'account';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $query = $input['query'] ?? null;
        $status = $input['status'] ?? null;
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 50);

        $builder = PurchaseInvoice::query()
            ->where('workspace_id', $wsId);

        if (! empty($query)) {
            $builder->where('invoice_id', 'like', "%{$query}%");
        }

        if (! empty($status)) {
            $builder->where('status', $status);
        }

        $bills = $builder->latest()->take($limit)->get();

        $safeBills = $bills->map(fn ($bill) => [
            'id' => $bill->id,
            'invoice_id' => $bill->invoice_id,
            'vendor_id' => $bill->vendor_id,
            'purchase_date' => optional($bill->purchase_date)->toDateString(),
            'due_date' => optional($bill->due_date)->toDateString(),
            'total_amount' => (float) $bill->total_amount,
            'status' => $bill->status,
        ]);

        $evidence = $bills->map(fn ($bill) => [
            'type' => 'bill',
            'id' => $bill->id,
            'label' => "{$bill->invoice_id} ({$bill->status}) - $".number_format((float) $bill->total_amount, 2),
            'route' => "/purchases/invoices/{$bill->id}",
        ])->all();

        $summary = sprintf('Found %d vendor bill(s).', $bills->count());

        return ToolResult::success($safeBills->toArray(), $summary, $evidence);
    }
}
