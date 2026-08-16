import React from 'react';
import { router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { SectionHeader } from '@/Components/UI/SectionHeader';

export default function EmployeeDashboard({ employee, attendance = [], leaves = [], payslips = [], events = [], policies = [] }: any) {
  const pending = leaves.filter((leave: any) => leave.status === 'pending').length;
  return <AppShell title="Employee Dashboard" breadcrumbs={[{ label: 'HRM' }, { label: 'My Dashboard' }]}><div className="space-y-6"><SectionHeader title={`Employee Dashboard — ${employee.name}`} description="Your attendance, leave requests, payslips, HR records, and company policy acknowledgements." badge={<Badge variant="purple" size="sm">{employee.employee_number}</Badge>} />
    <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4"><Metric title="Status" value={employee.status} /><Metric title="Basic Salary" value={Number(employee.basic_salary || 0).toFixed(2)} /><Metric title="Pending Leaves" value={pending} /><Metric title="Recent Attendance" value={attendance.length} /></div>
    <div className="grid xl:grid-cols-2 gap-5"><List title="Attendance" records={attendance} fields={['attendance_date', 'clock_in', 'clock_out', 'status']} /><List title="Leave Requests" records={leaves} fields={['starts_on', 'ends_on', 'days', 'status']} /><List title="Payslips" records={payslips} fields={['period_start', 'period_end', 'net_pay', 'status']} /><List title="My HR Records" records={events} fields={['kind', 'title', 'event_date', 'status']} /></div>
    <Card level={0} className="p-5"><h3 className="font-bold mb-4">Company Policies</h3><div className="space-y-3">{policies.map((policy: any) => <div key={policy.id} className="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl border border-[var(--border-subtle)]"><div><p className="font-semibold text-sm">{policy.title} <span className="text-[var(--text-tertiary)]">v{policy.version}</span></p><p className="text-xs text-[var(--text-tertiary)] mt-1">Effective {policy.effective_on || 'immediately'}</p></div>{policy.requires_acknowledgement && <Button size="sm" variant="primary" onClick={() => router.post(`/hrm/policies/${policy.id}/acknowledge`)}>Acknowledge</Button>}</div>)}</div></Card>
  </div></AppShell>;
}
function Metric({ title, value }: any) { return <Card level={0} className="p-5"><p className="text-xs text-[var(--text-tertiary)]">{title}</p><p className="text-xl font-bold mt-1 capitalize">{value}</p></Card>; }
function List({ title, records, fields }: any) { return <Card level={0} className="p-5"><h3 className="font-bold mb-4">{title}</h3><div className="space-y-2">{records.map((record: any) => <div key={record.id} className="grid grid-cols-2 sm:grid-cols-4 gap-2 p-3 rounded-xl border border-[var(--border-subtle)] text-xs">{fields.map((field: string) => <span key={field}>{String(record[field] ?? '—')}</span>)}</div>)}{!records.length && <p className="text-xs text-[var(--text-tertiary)]">No records.</p>}</div></Card>; }
