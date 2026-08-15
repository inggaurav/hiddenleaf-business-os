<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmLead;
use App\Models\PosSale;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;

class BusinessDashboardSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'business.dashboard.summary';
    }

    public function description(): string
    {
        return 'Get a high-level executive summary of the workspace business KPIs, sales, expenses, cashflow, POS volume, and active pipeline.';
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
        return null;
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();

        $totalSales = (float) SalesInvoice::where('workspace_id', $wsId)->where('status', 'posted')->sum('total_amount');
        $totalExpenses = (float) PurchaseInvoice::where('workspace_id', $wsId)->where('status', 'posted')->sum('total_amount');
        $netMargin = $totalSales - $totalExpenses;
        $openLeads = CrmLead::where('workspace_id', $wsId)->whereNull('converted_at')->count();
        $todayPos = (float) PosSale::where('workspace_id', $wsId)->whereDate('created_at', today())->sum('grand_total');

        $data = [
            'total_sales' => $totalSales,
            'total_expenses' => $totalExpenses,
            'net_operating_margin' => $netMargin,
            'open_leads' => $openLeads,
            'today_pos_volume' => $todayPos,
        ];

        $summary = sprintf(
            'Workspace Financial Summary: Total Sales: $%s | Total Expenses: $%s | Net Margin: $%s | Active Leads: %d | Today POS Sales: $%s',
            number_format($totalSales, 2),
            number_format($totalExpenses, 2),
            number_format($netMargin, 2),
            $openLeads,
            number_format($todayPos, 2)
        );

        return ToolResult::success($data, $summary, [
            ['type' => 'dashboard', 'label' => 'Executive Dashboard', 'route' => '/dashboard'],
        ]);
    }
}
