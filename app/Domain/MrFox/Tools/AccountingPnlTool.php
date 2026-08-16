<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;

class AccountingPnlTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'accounting.pnl';
    }

    public function description(): string
    {
        return 'Compute workspace Profit & Loss statement summarizing revenue, operating expenses, and net operating margin.';
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
        return 'account.reports';
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
        $orgId = $context->getOrganizationId();
        $wsId = $context->getWorkspaceId();

        $grossRevenue = (float) SalesInvoice::where('workspace_id', $wsId)
            ->whereIn('status', ['posted', 'sent', 'partial', 'paid'])
            ->sum('total_amount');

        $costAndExpenses = (float) PurchaseInvoice::where('workspace_id', $wsId)
            ->whereIn('status', ['posted', 'sent', 'partial', 'paid'])
            ->sum('total_amount');

        $netIncome = $grossRevenue - $costAndExpenses;
        $marginPercent = $grossRevenue > 0 ? round(($netIncome / $grossRevenue) * 100, 2) : 0;

        $data = [
            'gross_revenue' => $grossRevenue,
            'total_expenses' => $costAndExpenses,
            'net_profit' => $netIncome,
            'operating_margin_percent' => $marginPercent,
        ];

        $summary = sprintf(
            'P&L Summary: Gross Revenue: $%s | Total Expenses: $%s | Net Margin: $%s (%s%%)',
            number_format($grossRevenue, 2),
            number_format($costAndExpenses, 2),
            number_format($netIncome, 2),
            $marginPercent
        );

        return ToolResult::success($data, $summary, [
            ['type' => 'accounting', 'label' => 'Profit & Loss Report', 'route' => '/accounting/reports/profit-loss'],
        ]);
    }
}
