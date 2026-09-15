import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { FolderKanban, CheckSquare, AlertTriangle, ArrowLeft } from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { ProgressDistribution } from '@/Components/UI/Charts';
import { formatNumber, formatINR } from '@/lib/format';
import type { OperationsData, OverdueTask, ActiveProject } from './types';

interface Props {
  workspace: { id: number; name?: string };
  operations: OperationsData;
}

export default function OperationalAnalytics({ workspace, operations }: Props) {
  const { project_metrics, task_metrics, priority_distribution, overdue_tasks, active_projects } = operations;

  return (
    <AppShell>
      <Head title="Operational Analytics — Smart Analytics" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Link href="/smart-analytics/dashboard">
            <button className="rounded-lg bg-[var(--surface-3)] p-2 border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
              <ArrowLeft className="h-4 w-4 text-[var(--text-primary)]" />
            </button>
          </Link>
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Operational Analytics</h1>
            <p className="mt-0.5 text-sm text-[var(--text-secondary)]">Project delivery and task management</p>
          </div>
        </div>

        {/* KPI Row */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Card>
            <FolderKanban className="h-5 w-5 text-indigo-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(project_metrics.active)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Active Projects</p>
          </Card>
          <Card>
            <CheckSquare className="h-5 w-5 text-emerald-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(project_metrics.completed)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Completed Projects</p>
          </Card>
          <Card>
            <CheckSquare className="h-5 w-5 text-sky-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{task_metrics.completion_rate}%</p>
            <p className="text-xs text-[var(--text-tertiary)]">Task Completion</p>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{task_metrics.completed}/{task_metrics.total} tasks</p>
          </Card>
          <Card>
            <AlertTriangle className={`h-5 w-5 ${task_metrics.overdue > 0 ? 'text-red-400' : 'text-emerald-400'}`} />
            <p className={`mt-2 text-2xl font-bold ${task_metrics.overdue > 0 ? 'text-red-400' : 'text-[var(--text-primary)]'}`}>
              {formatNumber(task_metrics.overdue)}
            </p>
            <p className="text-xs text-[var(--text-tertiary)]">Overdue Tasks</p>
          </Card>
          <Card>
            <FolderKanban className="h-5 w-5 text-amber-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatINR(project_metrics.total_budget)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Total Budget</p>
          </Card>
        </div>

        {/* Priority Distribution + Overdue Tasks */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card padded={false}>
            <CardHeader title="Task Priority Distribution" />
            <CardBody>
              {priority_distribution.length === 0 ? (
                <p className="text-xs text-[var(--text-tertiary)]">No tasks found.</p>
              ) : (
                <ProgressDistribution items={priority_distribution} />
              )}
            </CardBody>
          </Card>

          <Card padded={false}>
            <CardHeader title="Overdue Tasks" />
            <CardBody>
              <div className="space-y-2">
                {(overdue_tasks ?? []).length === 0 && (
                  <div className="flex items-center justify-center gap-2 rounded-lg border border-dashed border-emerald-500/30 bg-emerald-500/5 p-4">
                    <CheckSquare className="h-4 w-4 text-emerald-400" />
                    <p className="text-xs text-emerald-400 font-medium">All tasks are on schedule!</p>
                  </div>
                )}
                {(overdue_tasks ?? []).map((t: OverdueTask) => (
                  <div key={t.id} className="flex items-center justify-between rounded-lg border border-red-500/20 bg-red-500/5 p-3">
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{t.title}</p>
                      <p className="text-xs text-[var(--text-tertiary)]">{t.project_name}</p>
                    </div>
                    <div className="text-right">
                      <Badge variant="danger">{t.priority}</Badge>
                      <p className="mt-1 text-xs text-red-400">Due: {t.due_on}</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Active Projects Grid */}
        <Card padded={false}>
          <CardHeader title="Active Projects" />
          <CardBody>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {(active_projects ?? []).length === 0 && (
                <p className="col-span-3 text-center text-xs text-[var(--text-tertiary)]">No active projects.</p>
              )}
              {(active_projects ?? []).map((p: ActiveProject) => (
                <div key={p.id} className="rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-2)] p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-[var(--text-primary)] truncate">{p.name}</p>
                    <Badge variant="neutral">{p.status}</Badge>
                  </div>
                  <div className="flex items-center justify-between text-xs text-[var(--text-secondary)]">
                    <span>{p.completed_tasks}/{p.total_tasks} tasks</span>
                    <span>Due: {p.due_on}</span>
                  </div>
                  {/* Progress bar */}
                  <div className="h-2 w-full overflow-hidden rounded-full bg-[var(--surface-3)]">
                    <div
                      className="h-full rounded-full bg-emerald-500 transition-all"
                      style={{ width: `${p.progress}%` }}
                    />
                  </div>
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-[var(--text-tertiary)]">Budget: {formatINR(p.budget)}</span>
                    <span className="font-medium text-emerald-400">{p.progress}%</span>
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      </div>
    </AppShell>
  );
}
