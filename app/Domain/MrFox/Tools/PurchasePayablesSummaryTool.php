<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\PurchaseInvoice;

class PurchasePayablesSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'purchase.payables.summary';
    }

    public function description(): string
    {
        return 'Calculate total accounts payable balance, outstanding vendor bills, and overdue obligations.';
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

        $postedBills = PurchaseInvoice::query()
            ->where('workspace_id', $wsId)
            ->whereIn('status', [1, 2])
            ->get();

        $totalPayables = (float) $postedBills->sum('total_amount');
        $overdueBills = $postedBills->filter(fn ($b) => $b->due_date && $b->due_date->isPast());
        $totalOverdue = (float) $overdueBills->sum('total_amount');

        $data = [
            'total_open_bills' => $postedBills->count(),
            'total_payables' => $totalPayables,
            'overdue_bills_count' => $overdueBills->count(),
            'total_overdue_amount' => $totalOverdue,
        ];

        $summary = sprintf(
            'Accounts Payable: $%s total obligations across %d bills ($%s overdue across %d bills).',
            number_format($totalPayables, 2),
            $postedBills->count(),
            number_format($totalOverdue, 2),
            $overdueBills->count()
        );

        $evidence = $overdueBills->take(5)->map(fn ($b) => [
            'type' => 'bill',
            'id' => $b->id,
            'label' => "{$b->invoice_id} - $" . number_format((float) $b->total_amount, 2) . ' (DUE)',
            'route' => "/purchases/invoices/{$b->id}",
        ])->values()->all();

        return ToolResult::success($data, $summary, $evidence);
    }
}
