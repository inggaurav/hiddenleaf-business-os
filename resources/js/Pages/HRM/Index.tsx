import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Card, CardHeader, CardBody, MetricCard } from '@/Components/UI/Card';
import { DataTable, Column, LaravelPaginator } from '@/Components/UI/DataTable';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { formatINR, formatNumber, formatDate, formatPercent } from '@/lib/format';
import {
  Users,
  UserCheck,
  CalendarOff,
  Briefcase,
  UserPlus,
  Clock,
  DollarSign,
  Building,
  TrendingUp,
  UserX,
} from 'lucide-react';

interface Employee {
  id: number;
  employee_number: string;
  name: string;
  email: string | null;
  joined_at: string;
  status: string;
}

interface DepartmentItem {
  name: string;
  value: number;
}

interface LeaveTodayItem {
  name: string;
  leave_type: string;
  days: number;
}

interface AbsentTodayItem {
  name: string;
  department: string;
}

interface Props {
  employees: LaravelPaginator<Employee> | Employee[];
  attendanceToday?: number;
  pendingLeaves?: number;
  payrollTotal?: number;
  stats?: Record<string, any>;
  metrics?: Record<string, any>;
  department_distribution?: DepartmentItem[];
  employees_on_leave_today?: LeaveTodayItem[];
  employees_without_attendance?: AbsentTodayItem[];
}

export default function HRMIndex({
  employees,
  attendanceToday = 0,
  pendingLeaves = 0,
  payrollTotal = 0,
  stats: propStats,
  metrics: propMetrics,
  department_distribution: propDeptDist,
  employees_on_leave_today: propLeaveToday,
  employees_without_attendance: propAbsentToday,
}: Props) {
  const stats = propStats || propMetrics || {};

  const activeCount = Number(stats.active_employees ?? stats.employees ?? (Array.isArray(employees) ? employees.length : employees?.total ?? 0));
  const presentCount = Number(stats.present_today ?? stats.attendance_today ?? attendanceToday);
  const onLeaveCount = Number(stats.on_leave_today ?? stats.pending_leaves ?? pendingLeaves);
  const openPositions = Number(stats.open_positions ?? 0);
  const deptCount = Number(stats.total_departments ?? stats.departments ?? 0);
  const attendanceRate = activeCount > 0 ? presentCount / activeCount : null;

  const deptDistribution: DepartmentItem[] =
    propDeptDist || stats.department_distribution || stats.departmentDistribution || [];
  const employeesOnLeave: LeaveTodayItem[] =
    propLeaveToday || stats.employees_on_leave_today || [];
  const employeesAbsent: AbsentTodayItem[] =
    propAbsentToday || stats.employees_without_attendance || [];

  const columns: Column<Employee>[] = [
    {
      header: 'Emp ID',
      accessorKey: 'employee_number',
      render: (row) => (
        <span className="font-mono text-xs text-[var(--text-secondary)]">
          {row.employee_number || `#${row.id}`}
        </span>
      ),
    },
    {
      header: 'Employee Name',
      accessorKey: 'name',
      render: (row) => (
        <span className="text-sm font-medium text-[var(--text-primary)]">
          {row.name}
        </span>
      ),
    },
    {
      header: 'Work Email',
      accessorKey: 'email',
      render: (row) => (
        <span className="text-xs text-[var(--text-secondary)]">
          {row.email || '—'}
        </span>
      ),
    },
    {
      header: 'Joined On',
      accessorKey: 'joined_at',
      render: (row) => (
        <span className="text-xs text-[var(--text-tertiary)] tabular-nums">
          {formatDate(row.joined_at)}
        </span>
      ),
    },
    {
      header: 'Status',
      accessorKey: 'status',
      render: (row) => (
        <Badge variant={row.status === 'active' ? 'success' : 'neutral'}>
          {row.status}
        </Badge>
      ),
    },
    {
      header: 'Action',
      render: () => (
        <Link href="/hrm/employees">
          <Button variant="ghost" size="sm">
            Manage
          </Button>
        </Link>
      ),
    },
  ];

  return (
    <AppShell title="HRM">
      <Head title="HRM — Workforce Management" />
      <div className="space-y-6">
        {/* 1. SectionHeader */}
        <SectionHeader
          title="Human Resources"
          description="Workforce operations, employee records, attendance, and payroll."
          actions={
            <Link href="/hrm/employees">
              <Button variant="neutral" size="sm" icon={<UserPlus className="w-4 h-4" />}>
                Add employee
              </Button>
            </Link>
          }
        />

        {/* 2. Row 1 — 4 Core MetricCards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Active employees"
            value={formatNumber(activeCount)}
            icon={<Users className="w-4 h-4" />}
          />
          <MetricCard
            title="Present today"
            value={formatNumber(presentCount)}
            trend={attendanceRate !== null ? formatPercent(attendanceRate) : undefined}
            trendDirection="up"
            icon={<UserCheck className="w-4 h-4" />}
          />
          <MetricCard
            title="On leave"
            value={formatNumber(onLeaveCount)}
            icon={<CalendarOff className="w-4 h-4" />}
          />
          <MetricCard
            title="Open positions"
            value={formatNumber(openPositions)}
            icon={<Briefcase className="w-4 h-4" />}
          />
        </div>

        {/* Row 2 — Org structure metrics (4 cards) */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Total Branch"
            value={stats.total_branches ?? 0}
            icon={<Building className="w-4 h-4" />}
            subtitle="Active branches"
          />
          <MetricCard
            title="Total Department"
            value={stats.total_departments ?? deptCount ?? 0}
            icon={<Briefcase className="w-4 h-4" />}
            subtitle="Across all branches"
          />
          <MetricCard
            title="Promotions"
            value={stats.total_promotions ?? 0}
            icon={<TrendingUp className="w-4 h-4" />}
            subtitle="This month"
            trendDirection="up"
          />
          <MetricCard
            title="Terminations"
            value={stats.terminations ?? 0}
            icon={<UserX className="w-4 h-4" />}
            subtitle="This month"
            trendDirection={(stats.terminations ?? 0) > 0 ? 'down' : 'neutral'}
          />
        </div>

        {/* 3 + 4. Primary Work Surface + Secondary Panel */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-3">
            <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              Active Workforce Directory
            </h2>
            <DataTable
              data={employees}
              columns={columns}
              keyExtractor={(row) => row.id}
              searchPlaceholder="Search employees by name or code..."
              emptyTitle="No employees found"
              emptyDescription="Add team members to start tracking workforce attendance and payroll."
            />
          </div>

          <div className="space-y-4">
            <Card level={0} padded={false}>
              <CardHeader title="Needs Action" subtitle="HR operations pending review" />
              <CardBody className="space-y-3">
                <div className="flex items-center justify-between text-xs py-1.5 border-b border-[var(--border-subtle)]">
                  <span className="text-[var(--text-secondary)]">Pending leave approvals</span>
                  <span className="font-bold text-[var(--text-primary)] tabular-nums">{onLeaveCount}</span>
                </div>
                <div className="flex items-center justify-between text-xs py-1.5 border-b border-[var(--border-subtle)]">
                  <span className="text-[var(--text-secondary)]">Upcoming holidays</span>
                  <span className="font-bold text-[var(--text-primary)] tabular-nums">{stats.upcoming_holidays ?? 0}</span>
                </div>
                <div className="flex items-center justify-between text-xs py-1.5">
                  <span className="text-[var(--text-secondary)]">Payroll this month</span>
                  <span className="font-bold text-[var(--text-primary)] tabular-nums">
                    {formatINR(stats.payroll_month ?? payrollTotal)}
                  </span>
                </div>
              </CardBody>
            </Card>

            <Card level={0} padded={false}>
              <CardHeader title="Organization" subtitle="Operational structural units" />
              <CardBody className="space-y-3 text-xs">
                <div className="flex items-center justify-between py-1 border-b border-[var(--border-subtle)]">
                  <span className="text-[var(--text-secondary)]">Departments</span>
                  <span className="font-medium text-[var(--text-primary)] tabular-nums">{deptCount}</span>
                </div>
                <div className="flex items-center justify-between py-1">
                  <span className="text-[var(--text-secondary)]">Attendance Rate</span>
                  <span className="font-medium text-emerald-400 tabular-nums">
                    {attendanceRate !== null ? formatPercent(attendanceRate) : '—'}
                  </span>
                </div>
              </CardBody>
            </Card>
          </div>
        </div>

        {/* 5. Department Distribution + On Leave / Absent Sections */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Department Distribution */}
          <div className="lg:col-span-1">
            <Card padded={false}>
              <CardHeader title="Department Distribution" subtitle="Employees per department" />
              <CardBody>
                <div className="space-y-3 max-h-72 overflow-y-auto pr-1">
                  {deptDistribution.map((dept, i) => {
                    const colors = [
                      '#8B5CF6',
                      '#3B82F6',
                      '#10B981',
                      '#F59E0B',
                      '#EF4444',
                      '#06B6D4',
                      '#F97316',
                      '#84CC16',
                    ];
                    const color = colors[i % colors.length];
                    const max = Math.max(...deptDistribution.map((d) => d.value), 1);
                    const pct = (dept.value / max) * 100;
                    return (
                      <div key={i} className="space-y-1">
                        <div className="flex justify-between text-xs">
                          <span className="text-[var(--text-primary)] font-medium">{dept.name}</span>
                          <span className="text-[var(--text-tertiary)] tabular-nums">{dept.value}</span>
                        </div>
                        <div className="h-2 rounded-full bg-[var(--surface-2)] overflow-hidden">
                          <div
                            className="h-full rounded-full transition-all"
                            style={{ width: `${pct}%`, background: color }}
                          />
                        </div>
                      </div>
                    );
                  })}
                  {deptDistribution.length === 0 && (
                    <p className="text-xs text-[var(--text-tertiary)] text-center py-6">
                      No department data
                    </p>
                  )}
                </div>
              </CardBody>
            </Card>
          </div>

          {/* On Leave Today + Absent Today (2-column grid in remaining 2 cols) */}
          <div className="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Employees on leave today */}
            <Card padded={false}>
              <CardHeader
                title="On Leave Today"
                subtitle={`${employeesOnLeave.length} employees`}
              />
              <CardBody>
                <div className="space-y-2 max-h-72 overflow-y-auto">
                  {employeesOnLeave.map((emp, i) => (
                    <div
                      key={i}
                      className="flex items-center justify-between py-2 border-b border-[var(--border-subtle)] last:border-0"
                    >
                      <div className="flex items-center gap-2.5 min-w-0">
                        <div className="w-7 h-7 rounded-full bg-[var(--surface-2)] border border-[var(--border-subtle)] flex items-center justify-center text-xs font-bold text-[var(--text-secondary)] shrink-0">
                          {emp.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                          <p className="text-[13px] font-medium text-[var(--text-primary)] truncate">
                            {emp.name}
                          </p>
                          <p className="text-[11px] text-[var(--text-tertiary)] truncate">
                            {emp.leave_type}
                          </p>
                        </div>
                      </div>
                      <Badge variant="warning">{emp.days}d</Badge>
                    </div>
                  ))}
                  {employeesOnLeave.length === 0 && (
                    <p className="text-xs text-[var(--text-tertiary)] text-center py-6">
                      No one on leave today
                    </p>
                  )}
                </div>
              </CardBody>
            </Card>

            {/* Employees without attendance */}
            <Card padded={false}>
              <CardHeader
                title="Absent Today"
                subtitle="Not marked attendance"
              />
              <CardBody>
                <div className="space-y-2 max-h-72 overflow-y-auto">
                  {employeesAbsent.map((emp, i) => (
                    <div
                      key={i}
                      className="flex items-center justify-between py-2 border-b border-[var(--border-subtle)] last:border-0"
                    >
                      <div className="flex items-center gap-2.5 min-w-0">
                        <div className="w-7 h-7 rounded-full bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-xs font-bold text-rose-400 shrink-0">
                          {emp.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                          <p className="text-[13px] font-medium text-[var(--text-primary)] truncate">
                            {emp.name}
                          </p>
                          <p className="text-[11px] text-[var(--text-tertiary)] truncate">
                            {emp.department}
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
                  {employeesAbsent.length === 0 && (
                    <p className="text-xs text-[var(--text-tertiary)] text-center py-6">
                      Everyone has marked attendance
                    </p>
                  )}
                </div>
              </CardBody>
            </Card>
          </div>
        </div>

        {/* 6. Quick Links Grid */}
        <div>
          <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">
            HR Modules & Shortcuts
          </h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {[
              { name: 'Employees', href: '/hrm/employees', icon: Users, desc: 'Directory & records' },
              { name: 'Attendance', href: '/hrm/attendance', icon: Clock, desc: 'Clock-in & logs' },
              { name: 'Leave Requests', href: '/hrm/leave', icon: CalendarOff, desc: 'Time off & balances' },
              { name: 'Payroll', href: '/hrm/payroll', icon: DollarSign, desc: 'Runs & payslips' },
            ].map((link) => (
              <Link href={link.href} key={link.name}>
                <div className="p-4 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] spring-transition">
                  <div className="flex items-center gap-2 mb-1">
                    <link.icon className="w-4 h-4 text-[var(--text-tertiary)]" />
                    <span className="text-sm font-medium text-[var(--text-primary)]">{link.name}</span>
                  </div>
                  <div className="text-xs text-[var(--text-tertiary)]">{link.desc}</div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </AppShell>
  );
}
