<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\TasklyTask;

class TasklyOverdueTasksTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'taskly.overdue.tasks';
    }

    public function description(): string
    {
        return 'Fetch all incomplete project tasks that have missed their due date, with priority and project details.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
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

        $overdue = TasklyTask::query()
            ->where('workspace_id', $wsId)
            ->whereNull('completed_at')
            ->where('due_on', '<', now())
            ->with(['project'])
            ->get();

        $evidence = $overdue->map(fn ($t) => [
            'type' => 'task',
            'id' => $t->id,
            'label' => "{$t->title} ({$t->priority}) - Project: {$t->project?->name}",
            'route' => "/tasks/{$t->id}",
        ])->all();

        $summary = sprintf('Identified %d overdue sprint task(s).', $overdue->count());

        return ToolResult::success($overdue->toArray(), $summary, $evidence);
    }
}
