<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmLead;
use App\Models\CrmNote;
use Illuminate\Support\Facades\DB;

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
        $orgId = $context->getOrganizationId();
        $wsId = $context->getWorkspaceId();
        $leadId = (int) ($input['lead_id'] ?? 0);
        $noteText = trim((string) ($input['note'] ?? ''));

        if (empty($noteText)) {
            return ToolResult::error('Note text content cannot be empty.');
        }

        $lead = CrmLead::where('workspace_id', $wsId)->where('id', $leadId)->first();
        if (! $lead) {
            return ToolResult::error("CRM Lead #{$leadId} not found in this workspace.");
        }

        $note = DB::transaction(function () use ($orgId, $wsId, $lead, $context, $noteText) {
            return CrmNote::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'subject_type' => CrmLead::class,
                'subject_id' => $lead->id,
                'body' => $noteText,
                'created_by' => $context->user->id,
            ]);
        });

        $summary = "Added note to CRM Lead '{$lead->name}' (#{$lead->id}).";

        $safeData = [
            'id' => $note->id,
            'lead_id' => $lead->id,
            'body' => $note->body,
            'created_at' => optional($note->created_at)->toIso8601String(),
        ];

        return ToolResult::success($safeData, $summary, [
            ['type' => 'lead', 'id' => $lead->id, 'label' => "Lead: {$lead->name}", 'route' => "/crm/leads/{$lead->id}"],
        ]);
    }
}
