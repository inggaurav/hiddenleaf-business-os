<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxMission;

class MissionsListTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'missions.list';
    }

    public function description(): string
    {
        return 'List all active, completed, or paused AI-governed missions for the current workspace.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'description' => 'Filter by status: draft, queued, running, paused, waiting_for_approval, completed, failed, cancelled'],
                'limit' => ['type' => 'integer', 'description' => 'Maximum missions to return (default: 10)'],
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
        $status = $input['status'] ?? null;
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 50);

        $missions = MrFoxMission::where('workspace_id', $wsId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->take($limit)
            ->get();

        $data = $missions->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'objective' => $m->objective,
            'status' => $m->status,
            'current_step' => $m->current_step,
            'progress' => $m->progress_summary,
            'created_at' => optional($m->created_at)->toIso8601String(),
        ])->all();

        $summary = sprintf('Found %d mission(s) in workspace.', count($data));

        return ToolResult::success($data, $summary, [
            ['type' => 'missions', 'label' => 'Missions Dashboard', 'route' => '/missions'],
        ]);
    }
}
