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
        return 'Summarize the active CRM sales pipeline, open deals by stage, conversion rates, and pipeline total value.';
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

        $openLeads = CrmLead::where('workspace_id', $wsId)->whereNull('converted_at')->count();
        $totalDeals = CrmDeal::where('workspace_id', $wsId)->count();
        $dealsValue = (float) CrmDeal::where('workspace_id', $wsId)->sum('price');
        $wonDeals = CrmDeal::where('workspace_id', $wsId)->where('status', 'Won')->count();

        $data = [
            'open_leads_count' => $openLeads,
            'total_deals_count' => $totalDeals,
            'total_pipeline_value' => $dealsValue,
            'won_deals_count' => $wonDeals,
        ];

        $summary = sprintf(
            'CRM Pipeline: %d active leads | %d total deals | Pipeline Value: $%s | Closed Won: %d',
            $openLeads,
            $totalDeals,
            number_format($dealsValue, 2),
            $wonDeals
        );

        return ToolResult::success($data, $summary, [
            ['type' => 'crm', 'label' => 'CRM Pipeline', 'route' => '/crm/leads'],
        ]);
    }
}
