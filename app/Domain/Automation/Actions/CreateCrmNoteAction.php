<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Domain\MrFox\RiskLevel;
use App\Models\AutomationRun;
use App\Models\CrmNote;

class CreateCrmNoteAction implements AutomationActionContract
{
    public function name(): string
    {
        return 'crm.add_note';
    }

    public function description(): string
    {
        return 'Add an internal note or activity log to a CRM lead or deal.';
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::SAFE;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['lead_id', 'note'],
            'properties' => [
                'lead_id' => ['type' => 'integer'],
                'note' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(AutomationRun $run, array $input): array
    {
        $wsId = $run->workspace_id;
        $leadId = (int) ($input['lead_id'] ?? data_get($run->trigger_payload, 'lead_id') ?? 0);
        $noteText = (string) ($input['note'] ?? 'Automated note recorded.');

        $note = CrmNote::create([
            'workspace_id' => $wsId,
            'lead_id' => $leadId,
            'note' => $noteText,
        ]);

        return [
            'success' => true,
            'data' => ['note_id' => $note->id, 'lead_id' => $leadId],
            'error' => null,
        ];
    }
}
