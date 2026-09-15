import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { formatINRPrecise, formatDate } from '@/lib/format';
import {
  FileText,
  CheckCircle2,
  Clock,
  ShieldCheck,
  Building2,
  X,
  Eye,
  TrendingDown,
  TrendingUp,
} from 'lucide-react';

interface PayslipLine {
  id?: number;
  name: string;
  type: 'earning' | 'deduction';
  amount: number | string;
}

interface Payslip {
  id: number;
  period_start: string;
  period_end: string;
  gross_pay: number | string;
  deductions: number | string;
  net_pay: number | string;
  status: string;
  lines?: PayslipLine[];
}

export default function EmployeeDashboard({
  employee,
  attendance = [],
  leaves = [],
  payslips = [],
  events = [],
  policies = [],
}: any) {
  const [selectedPayslip, setSelectedPayslip] = useState<Payslip | null>(null);
  const pending = leaves.filter((leave: any) => leave.status === 'pending').length;

  return (
    <AppShell title="Employee Dashboard" breadcrumbs={[{ label: 'HRM' }, { label: 'My Dashboard' }]}>
      <div className="space-y-6">
        <SectionHeader
          title={`Employee Dashboard — ${employee.name}`}
          description="Your attendance, leave requests, payslips, statutory deductions, and company policy acknowledgements."
          badge={<Badge variant="purple" size="sm">{employee.employee_number}</Badge>}
        />

        {/* 4 Metric Cards */}
        <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <Metric title="Employment Status" value={employee.status} icon={<Building2 className="w-4 h-4 text-emerald-400" />} />
          <Metric title="Basic Monthly Wage" value={formatINRPrecise(employee.basic_salary || 0)} icon={<FileText className="w-4 h-4 text-indigo-400" />} />
          <Metric title="Pending Leaves" value={pending} icon={<Clock className="w-4 h-4 text-amber-400" />} />
          <Metric title="Recent Attendance" value={`${attendance.length} days logged`} icon={<CheckCircle2 className="w-4 h-4 text-emerald-400" />} />
        </div>

        <div className="grid xl:grid-cols-2 gap-6">
          {/* Recent Attendance */}
          <Card level={0} padded={false}>
            <CardHeader title="Attendance History" subtitle="Recent check-in and check-out records" />
            <CardBody className="p-4">
              <div className="space-y-2">
                {attendance.map((record: any) => (
                  <div key={record.id} className="flex items-center justify-between p-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-1)] text-xs">
                    <div>
                      <p className="font-semibold text-[var(--text-primary)]">{formatDate(record.attendance_date)}</p>
                      <p className="text-[var(--text-tertiary)] mt-0.5">
                        In: {record.clock_in ? record.clock_in.substring(11, 16) : '—'} | Out: {record.clock_out ? record.clock_out.substring(11, 16) : '—'}
                      </p>
                    </div>
                    <Badge variant={record.status === 'present' ? 'success' : 'warning'}>{record.status}</Badge>
                  </div>
                ))}
                {!attendance.length && <p className="text-xs text-[var(--text-tertiary)] py-4 text-center">No attendance logged.</p>}
              </div>
            </CardBody>
          </Card>

          {/* Leave Requests */}
          <Card level={0} padded={false}>
            <CardHeader title="Leave Requests" subtitle="Time off requests and review statuses" />
            <CardBody className="p-4">
              <div className="space-y-2">
                {leaves.map((leave: any) => (
                  <div key={leave.id} className="flex items-center justify-between p-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-1)] text-xs">
                    <div>
                      <p className="font-semibold text-[var(--text-primary)]">{leave.type?.name || 'Leave'}</p>
                      <p className="text-[var(--text-tertiary)] mt-0.5">
                        {formatDate(leave.starts_on)} to {formatDate(leave.ends_on)} ({leave.days} days)
                      </p>
                    </div>
                    <Badge variant={leave.status === 'approved' ? 'success' : leave.status === 'rejected' ? 'danger' : 'warning'}>
                      {leave.status}
                    </Badge>
                  </div>
                ))}
                {!leaves.length && <p className="text-xs text-[var(--text-tertiary)] py-4 text-center">No leave requests found.</p>}
              </div>
            </CardBody>
          </Card>

          {/* Payslips & Statutory Compliance */}
          <Card level={0} padded={false}>
            <CardHeader
              title="Payslips & Compensation"
              subtitle="Monthly salary disbursements with statutory deductions (EPF, ESI, PT, TDS)"
            />
            <CardBody className="p-4">
              <div className="space-y-2">
                {payslips.map((payslip: Payslip) => (
                  <div key={payslip.id} className="flex items-center justify-between p-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-1)] text-xs hover:border-[var(--border-medium)] transition-colors">
                    <div className="space-y-0.5">
                      <p className="font-semibold text-[var(--text-primary)]">
                        Pay Period: {formatDate(payslip.period_start)} – {formatDate(payslip.period_end)}
                      </p>
                      <p className="text-[var(--text-tertiary)]">
                        Gross: {formatINRPrecise(payslip.gross_pay)} | Deductions: {formatINRPrecise(payslip.deductions)}
                      </p>
                    </div>
                    <div className="flex items-center gap-3">
                      <div className="text-right">
                        <p className="font-bold text-[var(--text-primary)] text-sm">{formatINRPrecise(payslip.net_pay)}</p>
                        <Badge variant={payslip.status === 'paid' ? 'success' : 'neutral'} size="sm">
                          {payslip.status}
                        </Badge>
                      </div>
                      <Button
                        size="sm"
                        variant="outline"
                        icon={<Eye className="w-3.5 h-3.5" />}
                        onClick={() => setSelectedPayslip(payslip)}
                      >
                        Inspect
                      </Button>
                    </div>
                  </div>
                ))}
                {!payslips.length && <p className="text-xs text-[var(--text-tertiary)] py-4 text-center">No payslips generated yet.</p>}
              </div>
            </CardBody>
          </Card>

          {/* HR Events / Records */}
          <Card level={0} padded={false}>
            <CardHeader title="HR Records & Timeline" subtitle="Promotions, awards, warnings, and lifecycle milestones" />
            <CardBody className="p-4">
              <div className="space-y-2">
                {events.map((event: any) => (
                  <div key={event.id} className="flex items-center justify-between p-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-1)] text-xs">
                    <div>
                      <p className="font-semibold text-[var(--text-primary)] capitalize">{event.kind}: {event.title}</p>
                      <p className="text-[var(--text-tertiary)] mt-0.5">{formatDate(event.event_date)}</p>
                    </div>
                    <Badge variant={event.status === 'completed' || event.status === 'approved' ? 'success' : 'neutral'}>
                      {event.status}
                    </Badge>
                  </div>
                ))}
                {!events.length && <p className="text-xs text-[var(--text-tertiary)] py-4 text-center">No HR events logged.</p>}
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Company Policies */}
        <Card level={0} className="p-5">
          <div className="flex items-center gap-2 mb-4">
            <ShieldCheck className="w-5 h-5 text-indigo-400" />
            <h3 className="font-bold text-[var(--text-primary)] text-sm">Company Governance & Policies</h3>
          </div>
          <div className="space-y-3">
            {policies.map((policy: any) => (
              <div key={policy.id} className="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-1)]">
                <div>
                  <p className="font-semibold text-sm text-[var(--text-primary)]">
                    {policy.title} <span className="text-[var(--text-tertiary)] text-xs font-normal">v{policy.version}</span>
                  </p>
                  <p className="text-xs text-[var(--text-tertiary)] mt-1">
                    Effective {policy.effective_on ? formatDate(policy.effective_on) : 'immediately'}
                  </p>
                </div>
                {policy.requires_acknowledgement && (
                  <Button size="sm" variant="primary" onClick={() => router.post(`/hrm/policies/${policy.id}/acknowledge`)}>
                    Acknowledge
                  </Button>
                )}
              </div>
            ))}
            {!policies.length && <p className="text-xs text-[var(--text-tertiary)] py-2">No active company policies.</p>}
          </div>
        </Card>
      </div>

      {/* Payslip Inspection Modal with Statutory Lines */}
      {selectedPayslip && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-fade-in">
          <div className="bg-[var(--surface-0)] border border-[var(--border-medium)] rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl space-y-0">
            {/* Modal Header */}
            <div className="flex items-center justify-between p-5 border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
              <div>
                <h3 className="font-bold text-base text-[var(--text-primary)]">Payslip Statement</h3>
                <p className="text-xs text-[var(--text-tertiary)] mt-0.5">
                  Period: {formatDate(selectedPayslip.period_start)} to {formatDate(selectedPayslip.period_end)} • Status: <span className="uppercase font-semibold text-indigo-400">{selectedPayslip.status}</span>
                </p>
              </div>
              <Button
                variant="ghost"
                size="sm"
                icon={<X className="w-4 h-4" />}
                onClick={() => setSelectedPayslip(null)}
              />
            </div>

            {/* Modal Content */}
            <div className="p-6 space-y-6 max-h-[75vh] overflow-y-auto text-xs">
              {/* Summary Cards */}
              <div className="grid grid-cols-3 gap-3">
                <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                  <div className="flex items-center gap-1.5 text-emerald-400">
                    <TrendingUp className="w-3.5 h-3.5" />
                    <span className="font-semibold text-[11px] uppercase tracking-wide">Gross Pay</span>
                  </div>
                  <p className="text-base font-bold text-emerald-300 mt-1">{formatINRPrecise(selectedPayslip.gross_pay)}</p>
                </div>
                <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20">
                  <div className="flex items-center gap-1.5 text-rose-400">
                    <TrendingDown className="w-3.5 h-3.5" />
                    <span className="font-semibold text-[11px] uppercase tracking-wide">Deductions</span>
                  </div>
                  <p className="text-base font-bold text-rose-300 mt-1">{formatINRPrecise(selectedPayslip.deductions)}</p>
                </div>
                <div className="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
                  <div className="flex items-center gap-1.5 text-indigo-400">
                    <CheckCircle2 className="w-3.5 h-3.5" />
                    <span className="font-semibold text-[11px] uppercase tracking-wide">Net Payable</span>
                  </div>
                  <p className="text-base font-bold text-indigo-300 mt-1">{formatINRPrecise(selectedPayslip.net_pay)}</p>
                </div>
              </div>

              {/* Line Items Table */}
              <div className="rounded-xl border border-[var(--border-subtle)] overflow-hidden">
                <div className="p-3 bg-[var(--surface-1)] border-b border-[var(--border-subtle)] flex items-center justify-between">
                  <h4 className="font-bold text-[var(--text-primary)]">Itemized Earnings & Statutory Deductions</h4>
                  <span className="text-[11px] text-[var(--text-tertiary)]">EPF, ESI, PT, and Income Tax TDS</span>
                </div>
                <table className="w-full text-left">
                  <thead className="bg-[var(--surface-2)] text-[11px] text-[var(--text-secondary)] border-b border-[var(--border-subtle)]">
                    <tr>
                      <th className="py-2 px-3">Component Description</th>
                      <th className="py-2 px-3">Classification</th>
                      <th className="py-2 px-3 text-right">Amount (₹)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border-subtle)]">
                    {selectedPayslip.lines?.map((line, idx) => (
                      <tr key={idx} className="hover:bg-[var(--surface-1)]">
                        <td className="py-2.5 px-3 font-medium text-[var(--text-primary)]">{line.name}</td>
                        <td className="py-2.5 px-3">
                          <Badge variant={line.type === 'earning' ? 'success' : 'danger'} size="sm">
                            {line.type}
                          </Badge>
                        </td>
                        <td className={`py-2.5 px-3 text-right font-semibold tabular-nums ${line.type === 'earning' ? 'text-emerald-400' : 'text-rose-400'}`}>
                          {line.type === 'earning' ? '+' : '-'}{formatINRPrecise(line.amount)}
                        </td>
                      </tr>
                    ))}
                    {(!selectedPayslip.lines || selectedPayslip.lines.length === 0) && (
                      <tr>
                        <td colSpan={3} className="py-4 text-center text-[var(--text-tertiary)]">
                          Standard salary disbursement without individual lines.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="p-4 border-t border-[var(--border-subtle)] bg-[var(--surface-1)] flex justify-end">
              <Button variant="outline" size="sm" onClick={() => setSelectedPayslip(null)}>
                Close Statement
              </Button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

function Metric({ title, value, icon }: any) {
  return (
    <Card level={0} className="p-4">
      <div className="flex items-center justify-between text-xs text-[var(--text-tertiary)]">
        <span>{title}</span>
        {icon}
      </div>
      <p className="text-lg font-bold mt-1 text-[var(--text-primary)] capitalize tabular-nums">{value}</p>
    </Card>
  );
}
