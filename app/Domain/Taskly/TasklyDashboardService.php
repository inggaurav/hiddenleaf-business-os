<?php

namespace App\Domain\Taskly;

use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\TasklyTimesheet;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TasklyDashboardService
{
    public function getMetrics(Workspace $workspace, ?int $userId = null): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        $projectQuery = TasklyProject::where('organization_id', $orgId)->where('workspace_id', $wsId);
        if ($userId) {
            $projectQuery->where(fn ($query) => $query->where('manager_id', $userId)->orWhereHas('members', fn ($members) => $members->where('users.id', $userId)));
        }
        $projects = $projectQuery->get();
        $projectIds = $projects->pluck('id');
        $totalProjects = $projects->count();
        $activeProjects = $projects->where('status', 'active')->count();
        $completedProjects = $projects->where('status', 'completed')->count();
        $onHoldProjects = $projects->where('status', 'on_hold')->count();

        $tasks = TasklyTask::where('organization_id', $orgId)->where('workspace_id', $wsId);
        if ($userId) {
            $tasks->whereIn('project_id', $projectIds);
        }
        $totalTasks = (clone $tasks)->count();
        $completedTasks = (clone $tasks)->whereNotNull('completed_at')->count();
        $openTasks = (clone $tasks)->whereNull('completed_at')->count();
        $overdueTasks = (clone $tasks)->whereNull('completed_at')->whereDate('due_on', '<', today())->count();

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        $taskPriority = [
            'critical' => (clone $tasks)->where('priority', 'critical')->count(),
            'high' => (clone $tasks)->where('priority', 'high')->count(),
            'medium' => (clone $tasks)->where('priority', 'medium')->count(),
            'low' => (clone $tasks)->where('priority', 'low')->count(),
        ];

        $totalHoursTracked = (float) TasklyTimesheet::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->when($userId, fn ($query) => $query->whereIn('project_id', $projectIds))
            ->where('status', 'approved')
            ->sum('hours');

        $openMilestones = (int) DB::table('taskly_milestones')
            ->join('taskly_projects', 'taskly_projects.id', '=', 'taskly_milestones.project_id')
            ->where('taskly_projects.organization_id', $orgId)
            ->where('taskly_projects.workspace_id', $wsId)
            ->when($userId, fn ($query) => $query->whereIn('taskly_projects.id', $projectIds))
            ->where('taskly_milestones.status', 'open')
            ->count();

        // Recent Tasks
        $recentTasks = (clone $tasks)->with(['project', 'stage'])->latest()->limit(6)->get()->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'priority' => $t->priority,
            'project_name' => $t->project->name ?? 'No Project',
            'stage_name' => $t->stage->name ?? 'Stage',
            'due_on' => $t->due_on,
            'is_completed' => ! empty($t->completed_at),
            'created_at' => $t->created_at->format('M d, Y'),
        ]);

        // Recent Projects
        $recentProjects = $projects->sortByDesc('created_at')->take(5)->values()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'status' => $p->status,
            'budget' => (float) $p->budget,
            'starts_on' => $p->starts_on,
            'due_on' => $p->due_on,
        ]);

        // Monthly Progress (last 6 months created vs completed)
        $monthlyProgress = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $year = $date->year;
            $month = $date->month;

            $created = TasklyTask::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->when($userId, fn ($query) => $query->whereIn('project_id', $projectIds))
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();

            $completed = TasklyTask::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->when($userId, fn ($query) => $query->whereIn('project_id', $projectIds))
                ->whereNotNull('completed_at')
                ->whereYear('completed_at', $year)
                ->whereMonth('completed_at', $month)
                ->count();

            $monthlyProgress[] = [
                'month' => $monthName,
                'created' => $created,
                'completed' => $completed,
            ];
        }

        return [
            'stats' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'completed_projects' => $completedProjects,
                'on_hold_projects' => $onHoldProjects,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'open_tasks' => $openTasks,
                'overdue_tasks' => $overdueTasks,
                'completion_rate' => $completionRate,
                'total_hours_tracked' => $totalHoursTracked,
                'open_milestones' => $openMilestones,
            ],
            'taskPriority' => $taskPriority,
            'monthlyProgress' => $monthlyProgress,
            'recentTasks' => $recentTasks,
            'recentProjects' => $recentProjects,
        ];
    }
}
