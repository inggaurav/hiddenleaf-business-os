<?php

namespace App\Http\Controllers;

use App\Domain\Taskly\TasklyDashboardService;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;
use App\Models\TasklyTimesheet;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TasklyController extends Controller
{
    public function dashboard(Request $request, TasklyDashboardService $dashboardService)
    {
        $workspace = $this->workspace($request, 'taskly.view');
        $data = $dashboardService->getMetrics($workspace);

        $metrics = [
            'projects' => $data['stats']['total_projects'],
            'active_projects' => $data['stats']['active_projects'],
            'tasks' => $data['stats']['total_tasks'],
            'completed_tasks' => $data['stats']['completed_tasks'],
            'overdue_tasks' => $data['stats']['overdue_tasks'],
            'open_milestones' => $data['stats']['open_milestones'],
            'approved_hours' => $data['stats']['total_hours_tracked'],
        ];

        return Inertia::render('Taskly/Dashboard', ['metrics' => $metrics] + $data);
    }

    public function index(Request $request)
    {
        $workspace = $this->workspace($request, 'taskly.view');
        $projects = TasklyProject::forWorkspace($workspace->organization_id, $workspace->id)->with(['stages', 'members'])->get();
        $costs = DB::table('taskly_timesheets as time')
            ->join('taskly_project_members as member', fn ($j) => $j->on('member.project_id', '=', 'time.project_id')->on('member.user_id', '=', 'time.user_id'))
            ->where('time.workspace_id', $workspace->id)
            ->where('time.status', 'approved')
            ->select('time.project_id', DB::raw('SUM(time.hours * member.hourly_rate) as actual_cost'))
            ->groupBy('time.project_id')
            ->pluck('actual_cost', 'project_id')
            ->map(fn ($cost) => (float) $cost);

        $tasks = TasklyTask::forWorkspace($workspace->organization_id, $workspace->id);

        return Inertia::render('Taskly/Index', [
            'projects' => $projects,
            'tasks' => (clone $tasks)->latest()->paginate(50),
            'costs' => $costs,
            'metrics' => [
                'projects' => $projects->count(),
                'active_projects' => $projects->where('status', 'active')->count(),
                'tasks' => (clone $tasks)->count(),
                'completed_tasks' => (clone $tasks)->whereNotNull('completed_at')->count(),
                'overdue_tasks' => (clone $tasks)->whereNull('completed_at')->whereDate('due_on', '<', today())->count(),
                'open_milestones' => DB::table('taskly_milestones')->join('taskly_projects', 'taskly_projects.id', '=', 'taskly_milestones.project_id')->where('taskly_projects.workspace_id', $workspace->id)->where('taskly_milestones.status', 'open')->count(),
                'approved_hours' => (float) DB::table('taskly_timesheets')->where('workspace_id', $workspace->id)->where('status', 'approved')->sum('hours'),
            ],
        ]);
    }

    public function storeProject(Request $request)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'manager_id' => ['nullable', 'integer'],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.name' => ['required', 'string'],
            'stages.*.is_complete' => ['boolean'],
        ]);

        $this->member($workspace, $data['manager_id'] ?? null);
        foreach ($data['members'] ?? [] as $member) {
            $this->member($workspace, $member['user_id']);
        }

        DB::transaction(function () use ($data, $workspace, $request) {
            $project = TasklyProject::create([
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'due_on' => $data['due_on'] ?? null,
                'budget' => $data['budget'] ?? 0,
                'manager_id' => $data['manager_id'] ?? null,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['stages'] as $position => $stage) {
                $project->stages()->create($stage + ['position' => $position]);
            }

            $memberPayload = [];
            foreach ($data['members'] ?? [] as $member) {
                $memberPayload[(int) $member['user_id']] = [
                    'hourly_rate' => $member['hourly_rate'] ?? 0,
                    'role' => 'member',
                ];
            }
            if (! empty($data['manager_id'])) {
                $managerId = (int) $data['manager_id'];
                $memberPayload[$managerId] = array_merge($memberPayload[$managerId] ?? ['hourly_rate' => 0], ['role' => 'manager']);
            }
            if ($memberPayload !== []) {
                $project->members()->syncWithoutDetaching($memberPayload);
            }
        });

        return back()->with('success', 'Project created.');
    }

    public function storeTask(Request $request)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'stage_id' => ['required', 'integer'],
            'milestone_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'due_on' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ]);
        $project = $this->project($workspace, $data['project_id']);
        TasklyStage::where('project_id', $project->id)->findOrFail($data['stage_id']);
        if ($data['assigned_to'] ?? null) {
            abort_unless($project->members()->where('users.id', $data['assigned_to'])->exists(), 422, 'Assignee is not a project member.');
        }
        TasklyTask::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Task created.');
    }

    public function moveTask(Request $request, TasklyTask $task, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $this->tenant($task, $workspace);
        $data = $request->validate(['stage_id' => ['required', 'integer']]);
        $stage = TasklyStage::where('project_id', $task->project_id)->findOrFail($data['stage_id']);
        DB::transaction(function () use ($task, $stage, $request, $workspace, $audit) {
            $task->update(['stage_id' => $stage->id, 'completed_at' => $stage->is_complete ? now() : null]);
            if ($stage->is_complete) {
                $audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'task.completed', 'taskly_task', (string) $task->id, critical: true);
            }
        });

        return back()->with('success', 'Task moved.');
    }

    public function milestone(Request $request)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $data = $request->validate(['project_id' => ['required', 'integer'], 'name' => ['required', 'string'], 'due_on' => ['nullable', 'date']]);
        $project = $this->project($workspace, $data['project_id']);
        DB::table('taskly_milestones')->insert($data + ['project_id' => $project->id, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Milestone created.');
    }

    public function timesheet(Request $request)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'user_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'description' => ['nullable', 'string'],
        ]);
        $project = $this->project($workspace, $data['project_id']);
        abort_unless($project->members()->where('users.id', $data['user_id'])->exists(), 422, 'Timesheet user is not a project member.');
        if ($data['task_id'] ?? null) {
            TasklyTask::forWorkspace($workspace->organization_id, $workspace->id)->where('project_id', $project->id)->findOrFail($data['task_id']);
        }
        TasklyTimesheet::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'status' => 'submitted']);

        return back()->with('success', 'Time submitted.');
    }

    public function approveTime(Request $request, TasklyTimesheet $timesheet, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $this->tenant($timesheet, $workspace);
        abort_unless($timesheet->status === 'submitted', 422, 'Timesheet already reviewed.');
        DB::transaction(function () use ($timesheet, $request, $workspace, $audit) {
            $timesheet->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'timesheet.approved', 'taskly_timesheet', (string) $timesheet->id, ['hours' => $timesheet->hours], critical: true);
        });

        return back()->with('success', 'Timesheet approved.');
    }

    public function comment(Request $request, TasklyTask $task)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $this->tenant($task, $workspace);
        $data = $request->validate(['body' => ['required', 'string']]);
        DB::table('taskly_comments')->insert(['task_id' => $task->id, 'user_id' => $request->user()->id, 'body' => $data['body'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Comment added.');
    }

    public function issue(Request $request)
    {
        $workspace = $this->workspace($request, 'taskly.manage');
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assigned_to' => ['nullable', 'integer'],
        ]);
        $project = $this->project($workspace, $data['project_id']);
        if ($data['task_id'] ?? null) {
            TasklyTask::forWorkspace($workspace->organization_id, $workspace->id)->where('project_id', $project->id)->findOrFail($data['task_id']);
        }
        if ($data['assigned_to'] ?? null) {
            abort_unless($project->members()->where('users.id', $data['assigned_to'])->exists(), 422, 'Assignee is not a project member.');
        }
        DB::table('taskly_issues')->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'status' => 'open', 'reported_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Issue reported.');
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }

    private function member(Workspace $workspace, ?int $id): void
    {
        if ($id) {
            abort_unless($workspace->members()->where('users.id', $id)->exists() || (int) $workspace->organization->owner_id === $id, 422, 'User is not a workspace member.');
        }
    }

    private function project(Workspace $workspace, int $id): TasklyProject
    {
        return TasklyProject::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($id);
    }

    private function tenant($model, Workspace $workspace): void
    {
        abort_unless((int) $model->organization_id === (int) $workspace->organization_id && (int) $model->workspace_id === (int) $workspace->id, 404);
    }
}
