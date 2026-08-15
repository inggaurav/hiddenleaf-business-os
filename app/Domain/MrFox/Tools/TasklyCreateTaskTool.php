<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;

class TasklyCreateTaskTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'taskly.create.task';
    }

    public function description(): string
    {
        return 'Create a new task in a workspace project with title, description, priority, and optional due date.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'title'],
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Target project unique ID',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Task headline or title',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Detailed description or specification',
                ],
                'priority' => [
                    'type' => 'string',
                    'description' => 'Priority: low, medium, high, critical (default: medium)',
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Target deadline in YYYY-MM-DD format',
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
        return RiskLevel::LOW;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $orgId = $context->getOrganizationId();
        $wsId = $context->getWorkspaceId();
        $projectId = (int) ($input['project_id'] ?? 0);

        $project = TasklyProject::where('workspace_id', $wsId)->where('id', $projectId)->first();
        if (! $project) {
            return ToolResult::error("Project #{$projectId} not found in this workspace.");
        }

        $stage = TasklyStage::where('project_id', $project->id)->first();
        if (! $stage) {
            $stage = TasklyStage::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'project_id' => $project->id,
                'name' => 'To Do',
                'order' => 0,
            ]);
        }

        $task = TasklyTask::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'project_id' => $project->id,
            'stage_id' => $stage->id,
            'created_by' => $context->user->id,
            'title' => $input['title'],
            'description' => $input['description'] ?? null,
            'priority' => $input['priority'] ?? 'medium',
            'due_on' => ! empty($input['due_date']) ? $input['due_date'] : null,
        ]);

        $summary = "Created new task '{$task->title}' in project '{$project->name}'.";

        return ToolResult::success($task->toArray(), $summary, [
            ['type' => 'task', 'id' => $task->id, 'label' => "Task: {$task->title}", 'route' => "/tasks/{$task->id}"],
        ]);
    }
}
