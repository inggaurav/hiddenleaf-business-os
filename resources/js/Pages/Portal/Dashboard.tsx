import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { BriefcaseBusiness, CreditCard, FileText, Receipt } from 'lucide-react';

const money = (value: unknown) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const rows = (value: any) => Array.isArray(value) ? value : value?.data || [];

export default function PortalDashboard(props: any) {
  const { portalRole, party, metrics = {}, invoices = [], proposals = [], returns = [], payments = [], notes = [], projects = [], projectPayments = [] } = props;
  const [payment, setPayment] = useState({ project_id: '', payment_date: new Date().toISOString().slice(0, 10), amount: '', payment_method: 'bank', reference: '', notes: '' });
  const isClient = portalRole === 'client';
  const cards = [
    ['Outstanding Balance', money(metrics.outstanding), CreditCard],
    [isClient ? 'Sales Invoices' : 'Purchase Invoices', metrics.invoices || 0, Receipt],
    [isClient ? 'Proposals' : 'Returns', isClient ? metrics.proposals || 0 : metrics.returns || 0, FileText],
    [isClient ? 'Projects' : 'Payments', isClient ? metrics.projects || 0 : metrics.payments || 0, BriefcaseBusiness],
  ];

  const submitPayment = (event: React.FormEvent) => {
    event.preventDefault();
    router.post('/portal/project-payments', payment, { onSuccess: () => setPayment({ ...payment, amount: '', reference: '', notes: '' }) });
  };

  return <AppShell title={`${isClient ? 'Client' : 'Vendor'} Dashboard`} breadcrumbs={[{ label: 'Portal' }, { label: 'Dashboard' }]}>
    <Head title={`${isClient ? 'Client' : 'Vendor'} Dashboard`} />
    <div className="space-y-6">
      <SectionHeader title={`Welcome, ${party?.name || 'Portal user'}`} description={isClient ? 'Review proposals, invoices, returns, payments, credit notes, and assigned projects.' : 'Review purchase invoices, returns, payments, and debit notes.'} badge={<Badge variant="purple" size="sm">{isClient ? 'Client Portal' : 'Vendor Portal'}</Badge>} />
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">{cards.map(([title, value, Icon]: any) => <Card key={title} level={0} className="p-5"><div className="flex items-center justify-between"><div><p className="text-xs text-[var(--text-tertiary)]">{title}</p><p className="text-2xl font-bold mt-1">{value}</p></div><Icon className="w-5 h-5 text-purple-400" /></div></Card>)}</div>

      {isClient && rows(proposals).length > 0 && <PortalTable title="Proposals" records={rows(proposals)} columns={['proposal_id', 'issue_date', 'total_amount', 'status']} action={(record: any) => ['draft', 'sent', 'pending'].includes(String(record.status)) ? <div className="flex gap-2"><Button size="sm" variant="primary" onClick={() => router.post(`/portal/proposals/${record.id}/decision`, { decision: 'accepted' })}>Accept</Button><Button size="sm" variant="ghost" onClick={() => router.post(`/portal/proposals/${record.id}/decision`, { decision: 'rejected' })}>Reject</Button></div> : null} />}
      <PortalTable title={isClient ? 'Invoices' : 'Purchase Invoices'} records={rows(invoices)} columns={['invoice_id', isClient ? 'issue_date' : 'purchase_date', 'due_date', 'total_amount', 'status']} />
      <PortalTable title="Returns" records={rows(returns)} columns={['return_id', 'date', 'total_amount', 'status']} />
      <PortalTable title="Payments" records={rows(payments)} columns={['payment_date', 'amount', 'payment_method', 'reference', 'status']} />
      <PortalTable title={isClient ? 'Credit Notes' : 'Debit Notes'} records={rows(notes)} columns={['note_number', 'date', 'amount', 'status']} />

      {isClient && <div className="grid xl:grid-cols-2 gap-5">
        <PortalTable title="Assigned Projects" records={rows(projects)} columns={['name', 'starts_on', 'due_on', 'budget', 'status']} />
        <Card level={0} className="p-5"><h3 className="font-bold mb-4">Submit Project Payment</h3><form onSubmit={submitPayment} className="grid sm:grid-cols-2 gap-3"><Select label="Project" value={payment.project_id} onChange={e => setPayment({ ...payment, project_id: e.target.value })} required><option value="">Select project</option>{rows(projects).map((project: any) => <option key={project.id} value={project.id}>{project.name}</option>)}</Select><Input label="Payment Date" type="date" value={payment.payment_date} onChange={e => setPayment({ ...payment, payment_date: e.target.value })} required /><Input label="Amount" type="number" min="0.01" step="0.01" value={payment.amount} onChange={e => setPayment({ ...payment, amount: e.target.value })} required /><Select label="Method" value={payment.payment_method} onChange={e => setPayment({ ...payment, payment_method: e.target.value })}><option value="bank">Bank</option><option value="card">Card</option><option value="online">Online</option><option value="cash">Cash</option><option value="other">Other</option></Select><Input label="Reference" value={payment.reference} onChange={e => setPayment({ ...payment, reference: e.target.value })} /><Input label="Notes" value={payment.notes} onChange={e => setPayment({ ...payment, notes: e.target.value })} /><div className="sm:col-span-2 flex justify-end"><Button type="submit" variant="primary">Submit Payment</Button></div></form></Card>
      </div>}
      {isClient && <PortalTable title="Project Payments" records={rows(projectPayments)} columns={['payment_date', 'amount', 'payment_method', 'reference', 'status']} />}
    </div>
  </AppShell>;
}

function PortalTable({ title, records, columns, action }: { title: string; records: any[]; columns: string[]; action?: (record: any) => React.ReactNode }) {
  return <Card level={0} className="overflow-hidden"><div className="p-4 border-b border-[var(--border-subtle)]"><h3 className="font-bold">{title}</h3></div><div className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]">{columns.map(column => <th key={column} className="p-3 capitalize">{column.replaceAll('_', ' ')}</th>)}{action && <th className="p-3">Actions</th>}</tr></thead><tbody>{records.length ? records.map((record, index) => <tr key={record.id || index} className="border-b border-[var(--border-subtle)]">{columns.map(column => <td key={column} className="p-3">{column.includes('amount') || column === 'budget' ? money(record[column]) : String(record[column] ?? '—')}</td>)}{action && <td className="p-3">{action(record)}</td>}</tr>) : <tr><td colSpan={columns.length + (action ? 1 : 0)} className="p-8 text-center text-[var(--text-tertiary)]">No records available.</td></tr>}</tbody></table></div></Card>;
}
