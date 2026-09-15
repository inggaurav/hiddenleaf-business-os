import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Users, UserPlus, CheckSquare, Clock, ArrowLeft } from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { ProgressDistribution } from '@/Components/UI/Charts';
import { formatNumber } from '@/lib/format';
import type { TeamData, TeamEmployee } from './types';

interface Props {
  workspace: { id: number; name?: string };
  team: TeamData;
}

export default function TeamPerformance({ workspace, team }: Props) {
  const { overview, departments, attendance, top_performers } = team;

  return (
    <AppShell>
      <Head title="Team Performance — Smart Analytics" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Link href="/smart-analytics/dashboard">
            <button className="rounded-lg bg-[var(--surface-3)] p-2 border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
              <ArrowLeft className="h-4 w-4 text-[var(--text-primary)]" />
            </button>
          </Link>
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Team Performance</h1>
            <p className="mt-0.5 text-sm text-[var(--text-secondary)]">Employee analytics and attendance overview</p>
          </div>
        </div>

        {/* KPI Row */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Card>
            <Users className="h-5 w-5 text-indigo-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(overview.total_employees)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Total Employees</p>
          </Card>
          <Card>
            <Users className="h-5 w-5 text-emerald-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(overview.active_employees)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Active</p>
          </Card>
          <Card>
            <UserPlus className="h-5 w-5 text-sky-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(overview.new_hires)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">New Hires (Month)</p>
          </Card>
          <Card>
            <CheckSquare className="h-5 w-5 text-amber-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(overview.tasks_completed_month)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Tasks Completed</p>
          </Card>
          <Card>
            <Clock className="h-5 w-5 text-purple-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{overview.attendance_rate}%</p>
            <p className="text-xs text-[var(--text-tertiary)]">Attendance Rate</p>
          </Card>
        </div>

        {/* Department Distribution + Attendance */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card padded={false}>
            <CardHeader title="Department Distribution" />
            <CardBody>
              {departments.length === 0 ? (
                <p className="text-xs text-[var(--text-tertiary)]">No departments configured.</p>
              ) : (
                <ProgressDistribution items={departments} />
              )}
            </CardBody>
          </Card>

          <Card padded={false}>
            <CardHeader title="Today's Attendance" />
            <CardBody>
              <ProgressDistribution
                items={[
                  { name: 'Present', value: attendance.present, color: 'bg-emerald-500' },
                  { name: 'Late', value: attendance.late, color: 'bg-amber-500' },
                  { name: 'Absent', value: attendance.absent, color: 'bg-red-500' },
                ]}
              />
            </CardBody>
          </Card>
        </div>

        {/* Top Performers Table */}
        <Card padded={false}>
          <CardHeader title="Employee Overview" />
          <CardBody>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--border-subtle)]">
                    <th className="pb-2 text-left font-medium text-[var(--text-tertiary)]">Employee</th>
                    <th className="pb-2 text-left font-medium text-[var(--text-tertiary)]">ID</th>
                    <th className="pb-2 text-left font-medium text-[var(--text-tertiary)]">Department</th>
                    <th className="pb-2 text-left font-medium text-[var(--text-tertiary)]">Designation</th>
                    <th className="pb-2 text-right font-medium text-[var(--text-tertiary)]">Tasks Done</th>
                    <th className="pb-2 text-left font-medium text-[var(--text-tertiary)]">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {top_performers.length === 0 && (
                    <tr>
                      <td colSpan={6} className="py-6 text-center text-xs text-[var(--text-tertiary)]">No employee data available.</td>
                    </tr>
                  )}
                  {top_performers.map((e: TeamEmployee) => (
                    <tr key={e.id} className="border-b border-[var(--border-subtle)] last:border-0">
                      <td className="py-2.5 text-[var(--text-primary)] font-medium">{e.name}</td>
                      <td className="py-2.5 text-[var(--text-secondary)]">{e.employee_number}</td>
                      <td className="py-2.5 text-[var(--text-secondary)]">{e.department}</td>
                      <td className="py-2.5 text-[var(--text-secondary)]">{e.designation}</td>
                      <td className="py-2.5 text-right text-[var(--text-primary)] font-semibold">{e.tasks_completed}</td>
                      <td className="py-2.5">
                        <Badge variant={e.status === 'active' ? 'success' : 'neutral'}>{e.status}</Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      </div>
    </AppShell>
  );
}
