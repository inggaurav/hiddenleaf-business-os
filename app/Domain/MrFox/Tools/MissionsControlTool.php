<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\Automation\Missions\MissionStateMachine;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxMission;

class MissionsControlTool implements MrFoxToolContract
{
    public function __construct(private ?MissionStateMachine $stateMachine = null)
    {
        $this->stateMachine = $stateMachine ?? app(MissionStateMachine::class);
    }

    public function name(): string
    {
        return 'missions.control';
    }

    public function description(): string
    {
        return 'Pause, resume, or cancel an active or queued mission.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['mission_id', 'action'],
            'properties' => [
                'mission_id' => ['type' => 'integer', 'description' => 'Target mission ID'],
                'action' => ['type' => 'string', 'enum' => ['pause', 'resume', 'cancel'], 'description' => 'Action to perform on mission'],
                'reason' => ['type' => 'string', 'description' => 'Optional reason'],
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
        return RiskLevel::MEDIUM;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $missionId = (int) ($input['mission_id'] ?? 0);
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        $reason = $input['reason'] ?? "User requested {$action}";

        $mission = MrFoxMission::where('workspace_id', $wsId)->where('id', $missionId)->first();
        if (! $mission) {
            return ToolResult::error("Mission #{$missionId} not found in this workspace.");
        }

        $targetStatus = match ($action) {
            'pause' => 'paused',
            'resume' => 'running',
            'cancel' => 'cancelled',
            default => null,
        };

        if (! $targetStatus) {
            return ToolResult::error("Invalid control action '{$action}'. Valid: pause, resume, cancel.");
        }

        try {
            $this->stateMachine->transition($mission, $targetStatus, $reason);

            return ToolResult::success([
                'mission_id' => $mission->id,
                'status' => $mission->status,
                'reason' => $reason,
            ], "Mission #{$mission->id} transitioned to '{$mission->status}'.", [
                ['type' => 'missions', 'id' => $mission->id, 'label' => "Mission: {$mission->name}", 'route' => '/missions'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
