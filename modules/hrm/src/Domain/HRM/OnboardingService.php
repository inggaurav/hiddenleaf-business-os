<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrEmployeeOnboarding;
use HiddenLeaf\Hrm\Models\HrEmployeeOnboardingTask;
use HiddenLeaf\Hrm\Models\HrOnboardingTask;
use HiddenLeaf\Hrm\Models\HrOnboardingTemplate;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function createTemplate(Workspace $ws, array $data, User $actor): HrOnboardingTemplate
    {
        $template = HrOnboardingTemplate::create([
            'organization_id' => $ws->organization_id,
            'workspace_id' => $ws->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);

        $this->audit->log(
            $actor->id,
            $ws->organization_id,
            $ws->id,
            'onboarding.template.created',
            'hr_onboarding_template',
            (string) $template->id,
            ['title' => $template->title]
        );

        return $template;
    }

    public function addTaskToTemplate(HrOnboardingTemplate $template, array $data): HrOnboardingTask
    {
        $maxPos = $template->tasks()->max('position') ?? 0;

        return HrOnboardingTask::create([
            'template_id' => $template->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'due_offset_days' => $data['due_offset_days'] ?? 0,
            'assigned_role' => $data['assigned_role'] ?? null,
            'requires_document' => (bool) ($data['requires_document'] ?? false),
            'position' => $data['position'] ?? ($maxPos + 1),
        ]);
    }

    public function startOnboarding(HrEmployee $employee, ?int $templateId, User $actor): HrEmployeeOnboarding
    {
        return DB::transaction(function () use ($employee, $templateId, $actor): HrEmployeeOnboarding {
            $template = $templateId
                ? HrOnboardingTemplate::find($templateId)
                : HrOnboardingTemplate::forWorkspace($employee->organization_id, $employee->workspace_id)->where('is_default', true)->first();

            $baseDate = $employee->joined_at ? Carbon::parse($employee->joined_at) : Carbon::today();

            $onboarding = HrEmployeeOnboarding::create([
                'organization_id' => $employee->organization_id,
                'workspace_id' => $employee->workspace_id,
                'employee_id' => $employee->id,
                'template_id' => $template?->id,
                'status' => 'in_progress',
                'started_on' => Carbon::today(),
                'target_completion_on' => $baseDate->copy()->addDays(30),
            ]);

            if ($template) {
                foreach ($template->tasks as $task) {
                    HrEmployeeOnboardingTask::create([
                        'onboarding_id' => $onboarding->id,
                        'onboarding_task_id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'category' => $task->category,
                        'due_on' => $baseDate->copy()->addDays($task->due_offset_days),
                        'status' => 'pending',
                    ]);
                }
            }

            $this->audit->log(
                $actor->id,
                $employee->organization_id,
                $employee->workspace_id,
                'onboarding.started',
                'hr_employee_onboarding',
                (string) $onboarding->id,
                ['employee_id' => $employee->id]
            );

            return $onboarding->load('tasks');
        });
    }

    public function completeTask(HrEmployeeOnboardingTask $task, User $actor, ?string $notes = null): HrEmployeeOnboardingTask
    {
        $task->update([
            'status' => 'completed',
            'completed_at' => Carbon::now(),
            'completed_by' => $actor->id,
            'notes' => $notes ?? $task->notes,
        ]);

        $onboarding = $task->onboarding;
        if ($onboarding) {
            $this->audit->log(
                $actor->id,
                $onboarding->organization_id,
                $onboarding->workspace_id,
                'onboarding.task.completed',
                'hr_employee_onboarding_task',
                (string) $task->id,
                ['title' => $task->title]
            );
        }

        return $task;
    }

    public function getProgress(HrEmployeeOnboarding $onboarding): int
    {
        $total = $onboarding->tasks()->count();
        if ($total === 0) {
            return 100;
        }

        $completed = $onboarding->tasks()->where('status', 'completed')->count();

        return (int) round(($completed / $total) * 100);
    }

    public function markComplete(HrEmployeeOnboarding $onboarding, User $actor): HrEmployeeOnboarding
    {
        $pendingCount = $onboarding->tasks()->where('status', '!=', 'completed')->count();
        abort_unless($pendingCount === 0, 422, 'All onboarding tasks must be completed before closing onboarding.');

        $onboarding->update([
            'status' => 'completed',
            'completed_on' => Carbon::today(),
        ]);

        $this->audit->log(
            $actor->id,
            $onboarding->organization_id,
            $onboarding->workspace_id,
            'onboarding.completed',
            'hr_employee_onboarding',
            (string) $onboarding->id,
            ['employee_id' => $onboarding->employee_id],
            critical: true
        );

        return $onboarding;
    }
}
