<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmActivity;
use App\Models\CrmLead;

class CrmAddNoteTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'crm.add.note';
    }

    public function description(): string
    {
        return 'Add a timeline note or activity record to a specific CRM lead.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['lead_id', 'note'],
            'properties' => [
                'lead_id' => [
                    'type' => 'integer',
                    'description' => 'Unique ID of the target CRM lead',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Text content of the note or meeting summary',
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
        return RiskLevel::LOW;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $leadId = (int) ($input['lead_id'] ?? 0);
        $noteText = trim($input['note'] ?? '');

        $lead = CrmLead::where('workspace_id', $wsId)->where('id', $leadId)->first();
        if (! $lead) {
            return ToolResult::error("CRM Lead #{$leadId} not found in this workspace.");
        }

        $activity = CrmActivity::create([
            'workspace_id' => $wsId,
            'lead_id' => $lead->id,
            'user_id' => $context->user->id,
            'type' => 'note',
            'description' => $noteText,
        ]);

        $summary = "Added note to CRM Lead '{$lead->name}' (#{$lead->id}).";

        return ToolResult::success($activity->toArray(), $summary, [
            ['type' => 'lead', 'id' => $lead->id, 'label' => "Lead: {$lead->name}", 'route' => "/crm/leads/{$lead->id}"],
        ]);
    }
}
