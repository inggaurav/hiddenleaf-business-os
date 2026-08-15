<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\SalesInvoice;

class SalesInvoiceSearchTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'sales.invoice.search';
    }

    public function description(): string
    {
        return 'Search sales invoices by invoice number, customer, status, or date range in the workspace.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional invoice number query',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by status: draft, posted, paid, cancelled',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum invoices to return (default 10)',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'sales.manage';
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
        $limit = min((int) ($input['limit'] ?? 10), 50);

        $builder = SalesInvoice::query()
            ->where('workspace_id', $wsId);

        if (! empty($query)) {
            $builder->where('invoice_id', 'like', "%{$query}%");
        }

        if (! empty($status)) {
            $builder->where('status', $status);
        }

        $invoices = $builder->latest()->take($limit)->get();

        $evidence = $invoices->map(fn ($inv) => [
            'type' => 'invoice',
            'id' => $inv->id,
            'label' => "{$inv->invoice_id} ({$inv->status}) - $" . number_format((float) $inv->total_amount, 2),
            'route' => "/sales/invoices/{$inv->id}",
        ])->all();

        $summary = sprintf('Found %d sales invoice(s).', $invoices->count());

        return ToolResult::success($invoices->toArray(), $summary, $evidence);
    }
}
