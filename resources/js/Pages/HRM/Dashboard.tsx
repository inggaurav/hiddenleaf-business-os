import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  Users,
  UserCheck,
  UserX,
  Calendar,
  Clock,
  Briefcase,
  Building,
  DollarSign,
  Plus,
  ArrowRight,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ProgressDistribution } from '@/Components/UI/Charts';

interface HrmStats {
  total_employees: number;
  active_employees: number;
  present_today: number;
  absent_today: number;
  absent_yesterday: number;
  on_leave_today: number;
  pending_leaves: number;
  total_branches: number;
  total_departments: number;
  total_designations: number;
  payroll_month: number;
}

interface DeptItem {
  name: string;
  value: number;
}

interface EmployeeItem {
  id: number;
  name: string;
  employee_number: string;
  email: string;
  joined_at: string;
  status: string;
  basic_salary: number;
}

interface LeaveItem {
  id: number;
  employee_name: string;
  leave_type: string;
  starts_on: string;
  ends_on: string;
  days: number;
  status: string;
}

interface HolidayItem {
  id: number;
  name: string;
  holiday_date: string;
  is_optional: boolean;
}

interface HrmDashboardProps {
  stats: HrmStats;
  departmentDistribution: DeptItem[];
  recentEmployees: EmployeeItem[];
  recentLeaves: LeaveItem[];
  upcomingHolidays: HolidayItem[];
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function HrmDashboard({
  stats,
  departmentDistribution = [],
  recentEmployees = [],
  recentLeaves = [],
  upcomingHolidays = [],
}: HrmDashboardProps) {
  return (
    <AppShell title="HRM Dashboard">
      <Head title="HRM & Workforce Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="HRM & Workforce Dashboard"
            description="Workforce attendance velocity, daily headcounts, leave authorizations, and payroll liability."
          />
          <div className="flex items-center gap-3">
            <Link
              href="/hrm/employees/list"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <Users className="h-4 w-4" />
              Employee Directory
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Employees"
            value={stats.total_employees}
            icon={<Users className="h-5 w-5 text-indigo-400" />}
            subtitle={`${stats.active_employees} active headcount`}
          />
          <MetricCard
            title="Present Today"
            value={stats.present_today}
            icon={<UserCheck className="h-5 w-5 text-emerald-400" />}
            trend={{
              value: stats.total_employees > 0 ? `${Math.round((stats.present_today / Math.max(stats.total_employees, 1)) * 100)}% attendance` : '0%',
              positive: true,
            }}
          />
          <MetricCard
            title="Absent Today"
            value={stats.absent_today}
            icon={<UserX className="h-5 w-5 text-rose-400" />}
            trend={{
              value: `${stats.absent_yesterday} yesterday`,
              neutral: true,
            }}
          />
          <MetricCard
            title="On Leave Today"
            value={stats.on_leave_today}
            icon={<Calendar className="h-5 w-5 text-amber-400" />}
            subtitle={`${stats.pending_leaves} requests pending review`}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Departments"
            value={stats.total_departments}
            icon={<Building className="h-5 w-5 text-sky-400" />}
            subtitle={`${stats.total_branches} physical branches`}
          />
          <MetricCard
            title="Designations"
            value={stats.total_designations}
            icon={<Briefcase className="h-5 w-5 text-violet-400" />}
            subtitle="Job roles configured"
          />
          <MetricCard
            title="Pending Leaves"
            value={stats.pending_leaves}
            icon={<Clock className="h-5 w-5 text-amber-400" />}
            trend={{
              value: stats.pending_leaves > 0 ? 'Requires action' : 'All clear',
              positive: stats.pending_leaves === 0,
            }}
          />
          <MetricCard
            title="Monthly Payroll"
            value={formatCurrency(stats.payroll_month)}
            icon={<DollarSign className="h-5 w-5 text-emerald-400" />}
            subtitle="Net pay this period"
          />
        </div>

        {/* Workforce Department Distribution */}
        <Card level={0} className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Department Headcount Distribution
              </h3>
              <p className="text-xs text-[var(--text-tertiary)]">
                Staff allocation across organizational units
              </p>
            </div>
            <Badge variant="neutral">{stats.total_employees} Total</Badge>
          </div>
          {departmentDistribution.length === 0 ? (
            <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
              No department records found.
            </div>
          ) : (
            <ProgressDistribution items={departmentDistribution} />
          )}
        </Card>

        {/* Recent HRM Activity Grid */}
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Recent Employees */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Hires
              </h3>
              <Link
                href="/hrm/employees/list"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentEmployees.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No employees onboarded yet.
                </div>
              ) : (
                recentEmployees.map((emp) => (
                  <div key={emp.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {emp.name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {emp.email} ({emp.employee_number})
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        Joined {emp.joined_at}
                      </div>
                    </div>
                    <Badge variant={emp.status === 'active' ? 'success' : 'neutral'} size="sm">
                      {emp.status}
                    </Badge>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Leave Requests */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Leave Applications
              </h3>
              <Badge variant="warning">{stats.pending_leaves} Pending</Badge>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentLeaves.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No leave requests submitted.
                </div>
              ) : (
                recentLeaves.map((leave) => (
                  <div key={leave.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {leave.employee_name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {leave.leave_type} • {leave.days} day(s)
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {leave.starts_on} to {leave.ends_on}
                      </div>
                    </div>
                    <Badge
                      variant={
                        leave.status === 'approved'
                          ? 'success'
                          : leave.status === 'rejected'
                          ? 'danger'
                          : 'warning'
                      }
                      size="sm"
                    >
                      {leave.status}
                    </Badge>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Upcoming Holidays */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Upcoming Holidays
              </h3>
              <Calendar className="h-4 w-4 text-violet-400" />
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {upcomingHolidays.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No upcoming holidays scheduled.
                </div>
              ) : (
                upcomingHolidays.map((holiday) => (
                  <div key={holiday.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[70%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {holiday.name}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {holiday.holiday_date}
                      </div>
                    </div>
                    {holiday.is_optional && (
                      <Badge variant="neutral" size="sm">
                        Optional
                      </Badge>
                    )}
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
