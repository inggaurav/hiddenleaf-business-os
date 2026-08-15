<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\TasklyProject;

class TasklySearchProjectsTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'taskly.search.projects';
    }

    public function description(): string
    {
        return 'List and search workspace projects, team members, active milestones, and completion status.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional project name query',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by status: ongoing, completed, on_hold',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'projects.manage';
    }

    public function requiredModule(): ?string
    {
        return 'taskly';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $query = $input['query'] ?? null;
        $status = $input['status'] ?? null;

        $builder = TasklyProject::query()
            ->where('workspace_id', $wsId);

        if (! empty($query)) {
            $builder->where('name', 'like', "%{$query}%");
        }

        if (! empty($status)) {
            $builder->where('status', $status);
        }

        $projects = $builder->latest()->get();

        $evidence = $projects->map(fn ($p) => [
            'type' => 'project',
            'id' => $p->id,
            'label' => "{$p->name} ({$p->status})",
            'route' => "/projects/{$p->id}",
        ])->all();

        $summary = sprintf('Found %d active project(s).', $projects->count());

        return ToolResult::success($projects->toArray(), $summary, $evidence);
    }
}
