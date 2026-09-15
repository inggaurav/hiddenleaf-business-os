import React from 'react';
import { Head, usePage, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card, MetricCard } from '@hiddenleaf/ui/Card';

export default function HrmDashboard() {
  const { metrics = {}, extended = {}, flash = {} } = usePage<any>().props;

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Dashboard" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}
      {flash?.error && (
        <div className="p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm">
          {flash.error}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">HRM & Workforce Operations</h1>
          <p className="text-sm text-slate-400">Complete workforce intelligence, recruitment pipeline, and employee lifecycle management.</p>
        </div>
        <div className="flex gap-2">
          <Link href="/hrm/employees">
            <Button variant="secondary" size="sm">Employees</Button>
          </Link>
          <Link href="/hrm/recruitment">
            <Button variant="primary" size="sm">Recruitment</Button>
          </Link>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <MetricCard label="Active Employees" value={metrics.active_employees ?? 0} />
        <MetricCard label="Present Today" value={metrics.present_today ?? 0} />
        <MetricCard label="On Leave Today" value={metrics.on_leave_today ?? 0} />
        <MetricCard label="Active Candidates" value={extended.candidates_active ?? 0} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card title="Quick Navigation" subtitle="HR modules">
          <div className="grid grid-cols-2 gap-2 pt-2">
            <Link href="/hrm/employees" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Employees</Link>
            <Link href="/hrm/recruitment" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Recruitment ATS</Link>
            <Link href="/hrm/onboarding" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Onboarding</Link>
            <Link href="/hrm/attendance" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Attendance</Link>
            <Link href="/hrm/leave" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Leave Requests</Link>
            <Link href="/hrm/timesheets" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Timesheets</Link>
            <Link href="/hrm/payroll" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Payroll Runs</Link>
            <Link href="/hrm/training" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Training</Link>
            <Link href="/hrm/disciplinary" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Disciplinary</Link>
            <Link href="/hrm/exit" className="p-3 rounded bg-slate-900 hover:bg-slate-800 text-sm font-medium border border-slate-800">Exit Management</Link>
          </div>
        </Card>

        <Card title="Operational Status" subtitle="Pending items requiring action">
          <div className="space-y-3 pt-2 text-sm">
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Onboardings In Progress</span>
              <span className="font-semibold text-white">{extended.onboarding_in_progress ?? 0}</span>
            </div>
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Active Training Programs</span>
              <span className="font-semibold text-white">{extended.training_active ?? 0}</span>
            </div>
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Open Disciplinary Cases</span>
              <span className="font-semibold text-rose-400">{extended.disciplinary_open ?? 0}</span>
            </div>
            <div className="flex justify-between py-2">
              <span className="text-slate-400">Draft Payroll Runs</span>
              <span className="font-semibold text-amber-400">{extended.payroll_runs_draft ?? 0}</span>
            </div>
          </div>
        </Card>

        <Card title="Workforce Structure" subtitle="Departments & hierarchy">
          <div className="space-y-3 pt-2 text-sm">
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Branches</span>
              <span className="font-semibold text-white">{metrics.branches_count ?? 0}</span>
            </div>
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Departments</span>
              <span className="font-semibold text-white">{metrics.departments_count ?? 0}</span>
            </div>
            <div className="flex justify-between py-2">
              <span className="text-slate-400">Designations</span>
              <span className="font-semibold text-white">{metrics.designations_count ?? 0}</span>
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}
