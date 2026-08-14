import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';

export default function BankTransactions({ transactions }:{transactions:{data:Array<any>}}) {
  return <AppShell><Head title="Accounting — Bank Transactions" /><div className="max-w-7xl mx-auto space-y-6 pb-12">
    <div><h1 className="text-2xl font-bold text-[var(--text-primary)]">Bank Transactions</h1><p className="text-xs text-[var(--text-secondary)] mt-1">Posted bank/cash journal movements with transaction-level reconciliation.</p></div>
    <Card level={0} className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Date</th><th>Account</th><th>Journal</th><th>Description</th><th>Debit</th><th>Credit</th><th>Reconciled</th><th className="pr-4">Action</th></tr></thead><tbody>{transactions.data.map((line:any)=><tr key={line.id} className="border-b border-[var(--border-subtle)]"><td className="p-4">{line.entry?.entry_date ?? '—'}</td><td>{line.account?.name ?? '—'}</td><td>{line.entry?.entry_number ?? '—'}</td><td>{line.description ?? line.entry?.description ?? '—'}</td><td>{line.debit}</td><td>{line.credit}</td><td>{line.is_reconciled?'Yes':'No'}</td><td className="pr-4">{!line.is_reconciled && <button onClick={()=>router.post(`/accounting/bank-transactions/${line.id}/mark-reconciled`)} className="text-emerald-400">Mark reconciled</button>}</td></tr>)}</tbody></table></div></Card>
  </div></AppShell>;
}
