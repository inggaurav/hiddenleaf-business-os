<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\Accounting\AccountReportService;
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
        $workspace = $context->workspace;

        $postedInvoices = SalesInvoice::query()
            ->where('workspace_id', $wsId)
            ->whereIn('status', [1, 2])
            ->get();

        $reportService = app(AccountReportService::class);
        $totalReceivables = 0.0;
        $overdueInvoices = [];

        foreach ($postedInvoices as $inv) {
            $outstanding = $workspace ? $reportService->salesOutstanding($workspace, $inv)->toFloat() : (float) $inv->total_amount;
            if ($outstanding > 0) {
                $totalReceivables += $outstanding;
                if ($inv->due_date && $inv->due_date->isPast()) {
                    $overdueInvoices[] = [
                        'invoice' => $inv,
                        'outstanding' => $outstanding,
                    ];
                }
            }
        }

        $totalOverdue = (float) array_sum(array_column($overdueInvoices, 'outstanding'));

        $data = [
            'total_open_invoices' => $postedInvoices->count(),
            'total_receivables' => $totalReceivables,
            'overdue_invoices_count' => count($overdueInvoices),
            'total_overdue_amount' => $totalOverdue,
        ];

        $summary = sprintf(
            'Accounts Receivable: $%s total outstanding across %d invoices ($%s overdue across %d invoices).',
            number_format($totalReceivables, 2),
            $postedInvoices->count(),
            number_format($totalOverdue, 2),
            count($overdueInvoices)
        );

        $evidence = array_map(fn ($item) => [
            'type' => 'invoice',
            'id' => $item['invoice']->id,
            'label' => "{$item['invoice']->invoice_id} - $" . number_format($item['outstanding'], 2) . ' (OVERDUE)',
            'route' => "/sales/invoices/{$item['invoice']->id}",
        ], array_slice($overdueInvoices, 0, 5));

        return ToolResult::success($data, $summary, $evidence);
    }
}
