<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Domain\MrFox\RiskLevel;
use App\Models\AutomationRun;
use App\Models\CrmLead;

class AssignLeadAction implements AutomationActionContract
{
    public function name(): string
    {
        return 'crm.assign_lead';
    }

    public function description(): string
    {
        return 'Assign a CRM lead to a team member or sales representative.';
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
            'required' => ['user_id'],
            'properties' => [
                'lead_id' => ['type' => 'integer'],
                'user_id' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(AutomationRun $run, array $input): array
    {
        $wsId = $run->workspace_id;
        $leadId = (int) ($input['lead_id'] ?? data_get($run->trigger_payload, 'lead_id') ?? 0);
        $userId = (int) ($input['user_id'] ?? 0);

        $lead = CrmLead::where('workspace_id', $wsId)->where('id', $leadId)->first();
        if (! $lead) {
            return ['success' => false, 'data' => [], 'error' => "Lead #{$leadId} not found."];
        }

        $lead->update(['user_id' => $userId]);

        return [
            'success' => true,
            'data' => ['lead_id' => $lead->id, 'assigned_user_id' => $userId],
            'error' => null,
        ];
    }
}
