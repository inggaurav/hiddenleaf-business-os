<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmDeal;
use App\Models\CrmLead;

class CrmPipelineSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'crm.pipeline.summary';
    }

    public function description(): string
    {
        return 'Get total deal pipeline value, deal count by stage, and conversion performance.';
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
        return 'crm.manage';
    }

    public function requiredModule(): ?string
    {
        return 'crm';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();

        $openDeals = CrmDeal::where('workspace_id', $wsId)->where('status', 'open')->get();
        $totalPipelineValue = (float) $openDeals->sum(fn ($d) => $d->value ?? $d->price ?? 0);
        $totalLeads = CrmLead::where('workspace_id', $wsId)->count();
        $convertedLeads = CrmLead::where('workspace_id', $wsId)->whereNotNull('converted_at')->count();

        $data = [
            'open_deals_count' => $openDeals->count(),
            'pipeline_value' => $totalPipelineValue,
            'total_leads' => $totalLeads,
            'converted_leads' => $convertedLeads,
            'conversion_rate_percent' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0,
        ];

        $summary = sprintf(
            'CRM Pipeline Summary: %d open deals totaling $%s in active pipeline. %d/%d leads converted (%s%%).',
            $openDeals->count(),
            number_format($totalPipelineValue, 2),
            $convertedLeads,
            $totalLeads,
            $data['conversion_rate_percent']
        );

        return ToolResult::success($data, $summary, [
            ['type' => 'crm', 'label' => 'CRM Deals Pipeline', 'route' => '/crm/deals'],
        ]);
    }
}
