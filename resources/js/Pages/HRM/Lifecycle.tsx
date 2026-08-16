import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';

const labels: Record<string, string> = { award: 'Awards', promotion: 'Promotions', resignation: 'Resignations', termination: 'Terminations', warning: 'Warnings', complaint: 'Complaints', transfer: 'Employee Transfers', acknowledgement: 'Acknowledgements', event: 'HR Events' };

export default function Lifecycle({ kind, employees = [], types = [], events, canManage = false }: any) {
  const title = labels[kind] || kind;
  const records = events?.data || events || [];
  const [type, setType] = useState({ name: '', description: '' });
  const [form, setForm] = useState({ employee_id: '', event_type_id: '', title: '', description: '', event_date: new Date().toISOString().slice(0, 10), effective_date: '' });
  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    router.post(`/hrm/lifecycle/${kind}`, form, { onSuccess: () => setForm({ ...form, title: '', description: '' }) });
  };

  return <AppShell title={title} breadcrumbs={[{ label: 'HRM' }, { label: title }]}><div className="space-y-6">
    <SectionHeader title={title} description={`Create, review, respond to, and complete employee ${title.toLowerCase()} records.`} badge={<Badge variant="purple" size="sm">{records.length} Records</Badge>} />
    {canManage && <div className="grid xl:grid-cols-[1fr_2fr] gap-5">
      <Card level={0} className="p-5 space-y-4"><h3 className="font-bold">Add {title.slice(0, -1)} Type</h3><form onSubmit={event => { event.preventDefault(); router.post(`/hrm/lifecycle/${kind}/types`, type, { onSuccess: () => setType({ name: '', description: '' }) }); }} className="space-y-3"><Input label="Type Name" value={type.name} onChange={event => setType({ ...type, name: event.target.value })} required /><Input label="Description" value={type.description} onChange={event => setType({ ...type, description: event.target.value })} /><Button type="submit" variant="secondary">Save Type</Button></form></Card>
      <Card level={0} className="p-5"><h3 className="font-bold mb-4">Create {title.slice(0, -1)} Record</h3><form onSubmit={submit} className="grid sm:grid-cols-2 gap-3"><Select label="Employee" value={form.employee_id} onChange={event => setForm({ ...form, employee_id: event.target.value })} required><option value="">Select employee</option>{employees.map((employee: any) => <option key={employee.id} value={employee.id}>{employee.employee_number} — {employee.name}</option>)}</Select><Select label="Type" value={form.event_type_id} onChange={event => setForm({ ...form, event_type_id: event.target.value })}><option value="">No type</option>{types.map((item: any) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select><Input label="Title" value={form.title} onChange={event => setForm({ ...form, title: event.target.value })} required /><Input label="Event Date" type="date" value={form.event_date} onChange={event => setForm({ ...form, event_date: event.target.value })} required /><Input label="Effective Date" type="date" value={form.effective_date} onChange={event => setForm({ ...form, effective_date: event.target.value })} /><Input label="Description" value={form.description} onChange={event => setForm({ ...form, description: event.target.value })} /><div className="sm:col-span-2 flex justify-end"><Button type="submit" variant="primary">Create Record</Button></div></form></Card>
    </div>}
    <Card level={0} className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-3">Employee</th><th>Type</th><th>Title</th><th>Date</th><th>Effective</th><th>Status</th><th>Response</th><th className="pr-3">Review</th></tr></thead><tbody>{records.map((record: any) => <tr key={record.id} className="border-b border-[var(--border-subtle)]"><td className="p-3">{record.employee_name}</td><td>{record.type_name || '—'}</td><td>{record.title}</td><td>{record.event_date}</td><td>{record.effective_date || '—'}</td><td><Badge size="sm" variant="neutral">{record.status}</Badge></td><td>{record.response || '—'}</td><td className="pr-3">{canManage ? <select className="input-shell" value={record.status} onChange={event => router.post(`/hrm/lifecycle/events/${record.id}/review`, { status: event.target.value, response: record.response || null })}><option value="pending">Pending</option><option value="acknowledged">Acknowledged</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="resolved">Resolved</option><option value="completed">Completed</option></select> : record.status}</td></tr>)}{!records.length && <tr><td colSpan={8} className="p-8 text-center text-[var(--text-tertiary)]">No records yet.</td></tr>}</tbody></table></Card>
  </div></AppShell>;
}
