import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  FolderKanban,
  CheckSquare,
  Clock,
  AlertTriangle,
  Percent,
  Plus,
  ArrowRight,
  TrendingUp,
  Flame,
  CheckCircle2,
  Calendar,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { SimpleBarChart, ProgressDistribution } from '@/Components/UI/Charts';

interface TasklyStats {
  total_projects: number;
  active_projects: number;
  completed_projects: number;
  on_hold_projects: number;
  total_tasks: number;
  completed_tasks: number;
  open_tasks: number;
  overdue_tasks: number;
  completion_rate: number;
  total_hours_tracked: number;
  open_milestones: number;
}

interface MonthlyTaskItem {
  month: string;
  created: number;
  completed: number;
}

interface TaskItem {
  id: number;
  title: string;
  priority: string;
  project_name: string;
  stage_name: string;
  due_on: string | null;
  is_completed: boolean;
  created_at: string;
}

interface ProjectItem {
  id: number;
  name: string;
  status: string;
  budget: number;
  starts_on: string | null;
  due_on: string | null;
}

interface TasklyDashboardProps {
  stats: TasklyStats;
  taskPriority: {
    critical: number;
    high: number;
    medium: number;
    low: number;
  };
  monthlyProgress: MonthlyTaskItem[];
  recentTasks: TaskItem[];
  recentProjects: ProjectItem[];
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function TasklyDashboard({
  stats,
  taskPriority,
  monthlyProgress = [],
  recentTasks = [],
  recentProjects = [],
}: TasklyDashboardProps) {
  const chartData = monthlyProgress.map((item) => ({
    label: item.month,
    value: item.created,
    secondaryValue: item.completed,
    formattedValue: `${item.created} created`,
    secondaryFormattedValue: `${item.completed} completed`,
  }));

  const priorityItems = [
    { name: 'Critical', value: taskPriority?.critical || 0, color: 'bg-rose-500' },
    { name: 'High', value: taskPriority?.high || 0, color: 'bg-amber-500' },
    { name: 'Medium', value: taskPriority?.medium || 0, color: 'bg-sky-500' },
    { name: 'Low', value: taskPriority?.low || 0, color: 'bg-emerald-500' },
  ];

  return (
    <AppShell title="Project Dashboard">
      <Head title="Project & Task Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="Project & Task Dashboard"
            description="Sprint task velocity, milestone fulfillment, resource hours, and project health."
          />
          <div className="flex items-center gap-3">
            <Link
              href="/taskly/projects/list"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <FolderKanban className="h-4 w-4" />
              Project Workspace
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Projects"
            value={stats.total_projects}
            icon={<FolderKanban className="h-5 w-5 text-indigo-400" />}
            subtitle={`${stats.active_projects} active, ${stats.completed_projects} completed`}
          />
          <MetricCard
            title="Total Tasks"
            value={stats.total_tasks}
            icon={<CheckSquare className="h-5 w-5 text-sky-400" />}
            subtitle={`${stats.open_tasks} open, ${stats.completed_tasks} completed`}
          />
          <MetricCard
            title="Completion Rate"
            value={`${stats.completion_rate}%`}
            icon={<Percent className="h-5 w-5 text-emerald-400" />}
            trend={{
              value: stats.completion_rate >= 70 ? 'High velocity' : 'Normal',
              positive: stats.completion_rate >= 70,
            }}
          />
          <MetricCard
            title="Overdue Tasks"
            value={stats.overdue_tasks}
            icon={<AlertTriangle className="h-5 w-5 text-rose-400" />}
            trend={{
              value: stats.overdue_tasks > 0 ? 'Requires attention' : 'On schedule',
              positive: stats.overdue_tasks === 0,
            }}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Active Sprints"
            value={stats.active_projects}
            icon={<TrendingUp className="h-5 w-5 text-emerald-400" />}
            subtitle="Projects in execution"
          />
          <MetricCard
            title="Tracked Hours"
            value={`${stats.total_hours_tracked} hrs`}
            icon={<Clock className="h-5 w-5 text-violet-400" />}
            subtitle="Approved timesheet log"
          />
          <MetricCard
            title="Open Milestones"
            value={stats.open_milestones}
            icon={<CheckCircle2 className="h-5 w-5 text-sky-400" />}
            subtitle="Deliverables pending completion"
          />
          <MetricCard
            title="On Hold Projects"
            value={stats.on_hold_projects}
            icon={<FolderKanban className="h-5 w-5 text-amber-400" />}
            subtitle="Paused project initiatives"
          />
        </div>

        {/* Charts Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Monthly Task Creation vs Completion */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                  Monthly Task Velocity
                </h3>
                <p className="text-xs text-[var(--text-tertiary)]">
                  Created vs completed task trends over the last 6 months
                </p>
              </div>
              <Badge variant="neutral">6 Months</Badge>
            </div>
            <SimpleBarChart
              data={chartData}
              primaryLabel="Created"
              secondaryLabel="Completed"
              primaryColor="bg-indigo-500"
              secondaryColor="bg-emerald-500"
              emptyMessage="No tasks logged in the last 6 months."
            />
          </Card>

          {/* Task Priority Distribution */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                  Task Priority Breakdown
                </h3>
                <p className="text-xs text-[var(--text-tertiary)]">
                  Active workload urgency distribution
                </p>
              </div>
              <Flame className="h-4 w-4 text-amber-400" />
            </div>
            <ProgressDistribution items={priorityItems} />
          </Card>
        </div>

        {/* Recent Tasks & Projects Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Recent Tasks */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Tasks
              </h3>
              <Link
                href="/taskly/projects/list"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentTasks.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No tasks created yet.
                </div>
              ) : (
                recentTasks.map((task) => (
                  <div key={task.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {task.title}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {task.project_name} • {task.stage_name}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        Due: {task.due_on ?? 'No due date'}
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge
                        variant={
                          task.priority === 'critical'
                            ? 'danger'
                            : task.priority === 'high'
                            ? 'warning'
                            : 'neutral'
                        }
                        size="sm"
                      >
                        {task.priority}
                      </Badge>
                      {task.is_completed && (
                        <CheckCircle2 className="h-4 w-4 text-emerald-400" />
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Projects */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Projects
              </h3>
              <Link
                href="/taskly/projects/list"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentProjects.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No projects created yet.
                </div>
              ) : (
                recentProjects.map((project) => (
                  <div key={project.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {project.name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)]">
                        Budget: {formatCurrency(project.budget)}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {project.starts_on ?? '—'} to {project.due_on ?? '—'}
                      </div>
                    </div>
                    <Badge
                      variant={
                        project.status === 'completed'
                          ? 'success'
                          : project.status === 'active'
                          ? 'neutral'
                          : 'warning'
                      }
                      size="sm"
                    >
                      {project.status}
                    </Badge>
                  </div>
                ))
              )}
            </div>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
