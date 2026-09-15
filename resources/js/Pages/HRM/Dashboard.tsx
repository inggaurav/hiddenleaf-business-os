import React from 'react';
import { Head, usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Card, CardHeader, CardBody, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { formatNumber, formatPercent } from '@/lib/format';
import { Users, UserCheck, CalendarOff, Briefcase, UserPlus } from 'lucide-react';

export default function HrmDashboard() {
  const { metrics = {}, extended = {}, flash = {} } = usePage<any>().props;

  const activeEmployees = Number(metrics.active_employees ?? metrics.employees ?? 0);
  const presentToday = Number(metrics.present_today ?? metrics.attendance_today ?? 0);
  const onLeaveToday = Number(metrics.on_leave_today ?? metrics.pending_leaves ?? 0);
  const activeCandidates = Number(extended.candidates_active ?? metrics.open_positions ?? metrics.departments_count ?? 0);
  const attendanceRate = activeEmployees > 0 ? presentToday / activeEmployees : null;

  return (
    <AppShell title="HRM">
      <Head title="HRM — Dashboard" />
      <div className="space-y-6">
        {flash?.success && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm">
            {flash.error}
          </div>
        )}

        {/* 1. SectionHeader */}
        <SectionHeader
          title="HRM"
          description="Workforce, payroll, and hiring operations."
          actions={
            <div className="flex items-center gap-2">
              <Link href="/hrm/employees">
                <Button variant="outline" size="sm">Employees</Button>
              </Link>
              <Link href="/hrm/recruitment">
                <Button variant="neutral" size="sm" icon={<UserPlus className="w-3.5 h-3.5" />}>Recruitment</Button>
              </Link>
            </div>
          }
        />

        {/* 2. Four Metrics */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Active employees"
            value={formatNumber(activeEmployees)}
            icon={<Users className="w-4 h-4" />}
          />
          <MetricCard
            title="Present today"
            value={formatNumber(presentToday)}
            trend={attendanceRate !== null ? formatPercent(attendanceRate) : undefined}
            trendDirection="up"
            icon={<UserCheck className="w-4 h-4" />}
          />
          <MetricCard
            title="On leave"
            value={formatNumber(onLeaveToday)}
            icon={<CalendarOff className="w-4 h-4" />}
          />
          <MetricCard
            title="Open positions"
            value={formatNumber(activeCandidates)}
            icon={<Briefcase className="w-4 h-4" />}
          />
        </div>

        {/* 3 + 4. Attention & Status */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-4">
            <Card level={0} padded={false}>
              <CardHeader title="Needs Action" subtitle="Operational items waiting on review" />
              <CardBody className="divide-y divide-[var(--border-subtle)] py-0">
                <div className="flex items-center justify-between py-3">
                  <span className="text-sm text-[var(--text-primary)]">Onboardings in progress</span>
                  <span className="text-sm font-semibold tabular-nums text-[var(--text-primary)]">
                    {formatNumber(extended.onboarding_in_progress ?? 0)}
                  </span>
                </div>
                <div className="flex items-center justify-between py-3">
                  <span className="text-sm text-[var(--text-primary)]">Active training programs</span>
                  <span className="text-sm font-semibold tabular-nums text-[var(--text-primary)]">
                    {formatNumber(extended.training_active ?? 0)}
                  </span>
                </div>
                <div className="flex items-center justify-between py-3">
                  <span className="text-sm text-[var(--text-primary)]">Open disciplinary cases</span>
                  <span className="text-sm font-semibold tabular-nums text-rose-400">
                    {formatNumber(extended.disciplinary_open ?? 0)}
                  </span>
                </div>
                <div className="flex items-center justify-between py-3">
                  <span className="text-sm text-[var(--text-primary)]">Draft payroll runs</span>
                  <span className="text-sm font-semibold tabular-nums text-amber-400">
                    {formatNumber(extended.payroll_runs_draft ?? 0)}
                  </span>
                </div>
              </CardBody>
            </Card>
          </div>

          <div className="space-y-4">
            <Card level={0} padded={false}>
              <CardHeader title="Workforce Structure" subtitle="Departments & hierarchy" />
              <CardBody className="space-y-3 text-xs">
                <div className="flex items-center justify-between py-1.5 border-b border-[var(--border-subtle)]">
                  <span className="text-[var(--text-secondary)]">Branches</span>
                  <span className="font-semibold text-[var(--text-primary)] tabular-nums">
                    {formatNumber(metrics.branches_count ?? 0)}
                  </span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-[var(--border-subtle)]">
                  <span className="text-[var(--text-secondary)]">Departments</span>
                  <span className="font-semibold text-[var(--text-primary)] tabular-nums">
                    {formatNumber(metrics.departments_count ?? 0)}
                  </span>
                </div>
                <div className="flex items-center justify-between py-1.5">
                  <span className="text-[var(--text-secondary)]">Designations</span>
                  <span className="font-semibold text-[var(--text-primary)] tabular-nums">
                    {formatNumber(metrics.designations_count ?? 0)}
                  </span>
                </div>
              </CardBody>
            </Card>
          </div>
        </div>

        {/* 5. Quick Links */}
        <div>
          <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">
            HR Operations
          </h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {[
              { name: 'Employees', href: '/hrm/employees' },
              { name: 'Recruitment ATS', href: '/hrm/recruitment' },
              { name: 'Onboarding', href: '/hrm/onboarding' },
              { name: 'Attendance', href: '/hrm/attendance' },
              { name: 'Leave Requests', href: '/hrm/leave' },
              { name: 'Timesheets', href: '/hrm/timesheets' },
              { name: 'Payroll Runs', href: '/hrm/payroll' },
              { name: 'Exit Management', href: '/hrm/exit' },
            ].map((link) => (
              <Link href={link.href} key={link.name}>
                <div className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] spring-transition text-sm font-medium text-[var(--text-primary)]">
                  {link.name}
                </div>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </AppShell>
  );
}
