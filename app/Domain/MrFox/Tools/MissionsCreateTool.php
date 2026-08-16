<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\Automation\Missions\MissionPlanner;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxBrandProfile;
use App\Models\MrFoxMission;

class MissionsCreateTool implements MrFoxToolContract
{
    public function __construct(private ?MissionPlanner $planner = null)
    {
        $this->planner = $planner ?? app(MissionPlanner::class);
    }

    public function name(): string
    {
        return 'missions.create';
    }

    public function description(): string
    {
        return 'Create and plan a new executive mission from an objective or goal.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'objective'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Short title for the mission'],
                'objective' => ['type' => 'string', 'description' => 'Detailed goal description for the mission'],
                'brand_profile_id' => ['type' => 'integer', 'description' => 'Optional Brand Profile ID'],
                'allowed_tools' => ['type' => 'array', 'description' => 'Optional list of allowed tools'],
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
        $orgId = $context->getOrganizationId();
        $userId = $context->user?->id;
        $name = (string) ($input['name'] ?? 'Executive Mission');
        $objective = (string) ($input['objective'] ?? '');
        $brandProfileId = ! empty($input['brand_profile_id']) ? (int) $input['brand_profile_id'] : MrFoxBrandProfile::where('workspace_id', $wsId)->where('is_default', true)->value('id');
        $allowedTools = is_array($input['allowed_tools'] ?? null) ? $input['allowed_tools'] : null;

        $mission = MrFoxMission::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'user_id' => $userId,
            'brand_profile_id' => $brandProfileId,
            'name' => $name,
            'objective' => $objective,
            'status' => 'draft',
            'allowed_tools' => $allowedTools,
        ]);

        // Generate plan
        $planResult = $this->planner->plan($context, $mission);

        $data = [
            'mission_id' => $mission->id,
            'name' => $mission->name,
            'status' => $mission->status,
            'steps_count' => count($planResult['steps']),
            'summary' => $planResult['plan_summary'],
        ];

        return ToolResult::success($data, "Created mission '{$name}' with ".count($planResult['steps']).' planned step(s).', [
            ['type' => 'missions', 'id' => $mission->id, 'label' => "Mission: {$mission->name}", 'route' => '/missions'],
        ]);
    }
}
