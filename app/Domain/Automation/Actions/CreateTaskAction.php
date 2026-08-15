<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Domain\MrFox\RiskLevel;
use App\Models\AutomationRun;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;

class CreateTaskAction implements AutomationActionContract
{
    public function name(): string
    {
        return 'tasks.create_task';
    }

    public function description(): string
    {
        return 'Create a follow-up or restock task in Taskly.';
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
            'required' => ['title'],
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'priority' => ['type' => 'string'],
                'project_id' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(AutomationRun $run, array $input): array
    {
        $wsId = $run->workspace_id;
        $orgId = $run->organization_id;
        $title = (string) ($input['title'] ?? 'Automation Generated Task');
        $desc = (string) ($input['description'] ?? "Created automatically from trigger: {$run->trigger_event}");
        $priority = $input['priority'] ?? 'medium';

        $project = null;
        if (! empty($input['project_id'])) {
            $project = TasklyProject::where('workspace_id', $wsId)->where('id', (int) $input['project_id'])->first();
        }
        if (! $project) {
            $project = TasklyProject::where('workspace_id', $wsId)->first();
        }

        if (! $project) {
            $project = TasklyProject::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => 'General Operations',
                'status' => 'ongoing',
            ]);
        }

        $stage = TasklyStage::where('project_id', $project->id)->first();
        if (! $stage) {
            $stage = TasklyStage::create([
                'project_id' => $project->id,
                'name' => 'Todo',
                'position' => 1,
            ]);
        }

        $task = TasklyTask::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'project_id' => $project->id,
            'stage_id' => $stage->id,
            'title' => $title,
            'description' => $desc,
            'priority' => $priority,
            'due_on' => now()->addDays(2)->toDateString(),
        ]);

        return [
            'success' => true,
            'data' => [
                'task_id' => $task->id,
                'title' => $task->title,
                'project_id' => $project->id,
                'stage_id' => $stage->id,
            ],
            'error' => null,
        ];
    }
}
