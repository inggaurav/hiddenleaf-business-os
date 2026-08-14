import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';

export default function TransactionShow({kind,transaction}:{kind:'revenue'|'expense';transaction:any}) {
  const base=`/accounting/${kind==='revenue'?'revenues':'expenses'}/${transaction.id}`;
  return <AppShell><Head title={`Accounting — ${kind}`}/><div className="max-w-4xl mx-auto space-y-6 pb-12">
    <div className="flex justify-between gap-4"><div><h1 className="text-2xl font-bold text-[var(--text-primary)] capitalize">{kind} · {transaction.reference ?? `#${transaction.id}`}</h1><p className="text-xs text-[var(--text-secondary)] mt-1">Financial transaction lifecycle and posting evidence.</p></div><div className="flex gap-2">{transaction.status==='draft'&&<Button variant="secondary" onClick={()=>router.post(`${base}/approve`)}>Approve</Button>}{transaction.status==='approved'&&<Button variant="primary" onClick={()=>router.post(`${base}/post`)}>Post to Ledger</Button>}</div></div>
    <Card level={0} className="p-5 grid sm:grid-cols-2 gap-4 text-sm"><div><div className="text-[var(--text-tertiary)] text-xs">Status</div><div className="capitalize">{transaction.status}</div></div><div><div className="text-[var(--text-tertiary)] text-xs">Amount</div><div>{transaction.amount}</div></div><div><div className="text-[var(--text-tertiary)] text-xs">Date</div><div>{transaction.date}</div></div><div><div className="text-[var(--text-tertiary)] text-xs">Method</div><div className="capitalize">{transaction.payment_method?.replace('_',' ')}</div></div><div><div className="text-[var(--text-tertiary)] text-xs">Account</div><div>{transaction.account?.name ?? 'Not assigned'}</div></div><div><div className="text-[var(--text-tertiary)] text-xs">Category</div><div>{transaction.category?.name ?? 'Uncategorized'}</div></div><div className="sm:col-span-2"><div className="text-[var(--text-tertiary)] text-xs">Description</div><div>{transaction.description ?? '—'}</div></div>{transaction.journal_entry && <div className="sm:col-span-2"><div className="text-[var(--text-tertiary)] text-xs">Journal</div><div>{transaction.journal_entry.entry_number}</div></div>}</Card>
  </div></AppShell>;
}
