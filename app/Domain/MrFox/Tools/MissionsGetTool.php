<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxMission;

class MissionsGetTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'missions.get';
    }

    public function description(): string
    {
        return 'Retrieve full details, execution steps, and progress observations for a specific mission.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['mission_id'],
            'properties' => [
                'mission_id' => ['type' => 'integer', 'description' => 'The unique ID of the mission'],
            ],
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
        $missionId = (int) ($input['mission_id'] ?? 0);

        $mission = MrFoxMission::where('workspace_id', $wsId)->where('id', $missionId)->first();
        if (! $mission) {
            return ToolResult::error("Mission #{$missionId} not found in this workspace.");
        }

        $steps = $mission->steps()->get()->map(fn ($s) => [
            'id' => $s->id,
            'sequence' => $s->sequence,
            'tool_name' => $s->tool_name,
            'status' => $s->status,
            'observation' => $s->observation,
            'approval_proposal_id' => $s->approval_proposal_id,
        ])->all();

        $data = [
            'id' => $mission->id,
            'name' => $mission->name,
            'objective' => $mission->objective,
            'status' => $mission->status,
            'current_step' => $mission->current_step,
            'progress' => $mission->progress_summary,
            'steps' => $steps,
        ];

        $summary = "Mission #{$mission->id} ({$mission->name}) — {$mission->status}, on step {$mission->current_step}/".count($steps).'.';

        return ToolResult::success($data, $summary, [
            ['type' => 'missions', 'id' => $mission->id, 'label' => "Mission: {$mission->name}", 'route' => '/missions'],
        ]);
    }
}
