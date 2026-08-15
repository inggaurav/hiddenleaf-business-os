<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmLead;

class CrmGetLeadTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'crm.get.lead';
    }

    public function description(): string
    {
        return 'Retrieve complete details, activities, notes, and status for a specific CRM lead by ID.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['lead_id'],
            'properties' => [
                'lead_id' => [
                    'type' => 'integer',
                    'description' => 'The unique ID of the CRM lead',
                ],
            ],
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
        $leadId = (int) ($input['lead_id'] ?? 0);

        $lead = CrmLead::query()
            ->where('workspace_id', $wsId)
            ->where('id', $leadId)
            ->with(['stage', 'pipeline'])
            ->first();

        if (! $lead) {
            return ToolResult::error("CRM Lead with ID #{$leadId} not found in this workspace.");
        }

        $summary = sprintf(
            'CRM Lead: %s | Company: %s | Stage: %s | Estimated Value: $%s',
            $lead->name,
            $lead->company ?? 'N/A',
            $lead->stage?->name ?? 'Default',
            number_format($lead->estimated_value ?? 0, 2)
        );

        return ToolResult::success($lead->toArray(), $summary, [
            ['type' => 'lead', 'id' => $lead->id, 'label' => "Lead: {$lead->name}", 'route' => "/crm/leads/{$lead->id}"],
        ]);
    }
}
