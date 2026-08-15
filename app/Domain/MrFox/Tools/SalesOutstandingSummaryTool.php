<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\SalesInvoice;

class SalesOutstandingSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'sales.outstanding.summary';
    }

    public function description(): string
    {
        return 'Calculate total accounts receivable balance, outstanding customer invoices, and overdue amounts.';
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

        $postedInvoices = SalesInvoice::query()
            ->where('workspace_id', $wsId)
            ->where('status', 'posted')
            ->get();

        $totalReceivables = (float) $postedInvoices->sum('total_amount');
        $overdueInvoices = $postedInvoices->filter(fn ($inv) => $inv->due_date && $inv->due_date->isPast());
        $totalOverdue = (float) $overdueInvoices->sum('total_amount');

        $data = [
            'total_open_invoices' => $postedInvoices->count(),
            'total_receivables' => $totalReceivables,
            'overdue_invoices_count' => $overdueInvoices->count(),
            'total_overdue_amount' => $totalOverdue,
        ];

        $summary = sprintf(
            'Accounts Receivable: $%s total outstanding across %d invoices ($%s overdue across %d invoices).',
            number_format($totalReceivables, 2),
            $postedInvoices->count(),
            number_format($totalOverdue, 2),
            $overdueInvoices->count()
        );

        $evidence = $overdueInvoices->take(5)->map(fn ($inv) => [
            'type' => 'invoice',
            'id' => $inv->id,
            'label' => "{$inv->invoice_id} - $" . number_format((float) $inv->total_amount, 2) . ' (OVERDUE)',
            'route' => "/sales/invoices/{$inv->id}",
        ])->values()->all();

        return ToolResult::success($data, $summary, $evidence);
    }
}
