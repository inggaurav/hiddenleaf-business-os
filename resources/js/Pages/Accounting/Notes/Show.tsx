import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';

export default function NoteShow({kind,note}:{kind:'credit'|'debit';note:any}) {
  const base=`/accounting/${kind==='credit'?'credit-notes':'debit-notes'}/${note.id}`;
  return <AppShell><Head title={`Accounting — ${kind} note`}/><div className="max-w-4xl mx-auto space-y-6 pb-12">
    <div className="flex justify-between gap-4"><div><h1 className="text-2xl font-bold text-[var(--text-primary)] capitalize">{kind} Note #{note.id}</h1><p className="text-xs text-[var(--text-secondary)] mt-1">Immutable adjustment note linked to the underlying commercial document.</p></div>{note.status==='pending'&&<Button variant="primary" onClick={()=>router.post(`${base}/approve`)}>Approve</Button>}</div>
    <Card level={0} className="p-5 grid sm:grid-cols-2 gap-4 text-sm"><div><span className="text-[var(--text-tertiary)] text-xs block">Status</span><span className="capitalize">{note.status}</span></div><div><span className="text-[var(--text-tertiary)] text-xs block">Amount</span>{note.amount}</div><div><span className="text-[var(--text-tertiary)] text-xs block">Date</span>{note.date}</div><div><span className="text-[var(--text-tertiary)] text-xs block">Party</span>{kind==='credit'?(note.customer?.name ?? '—'):(note.vendor?.name ?? '—')}</div><div><span className="text-[var(--text-tertiary)] text-xs block">Document</span>{kind==='credit'?(note.invoice?.invoice_id ?? '—'):(note.purchase_invoice?.invoice_id ?? note.purchaseInvoice?.invoice_id ?? '—')}</div><div><span className="text-[var(--text-tertiary)] text-xs block">Journal</span>{note.journal_entry?.entry_number ?? note.journalEntry?.entry_number ?? '—'}</div><div className="sm:col-span-2"><span className="text-[var(--text-tertiary)] text-xs block">Description</span>{note.description ?? '—'}</div></Card>
  </div></AppShell>;
}
