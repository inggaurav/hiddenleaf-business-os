import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';

export default function PartyDetail({kind,data}:{kind:'customer'|'vendor';data:any}) {
  const party=kind==='customer'?data.customer:data.vendor;
  return <AppShell><Head title={`Accounting — ${party?.name ?? kind}`}/><div className="max-w-6xl mx-auto space-y-6 pb-12">
    <div><h1 className="text-2xl font-bold text-[var(--text-primary)]">{party?.name}</h1><p className="text-xs text-[var(--text-secondary)] mt-1 capitalize">{kind} account statement · balance {data.balance}</p></div>
    <Card level={0} className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Date</th><th>Type</th><th>Reference</th><th>Debit</th><th>Credit</th><th>Status</th></tr></thead><tbody>{(data.transactions??[]).map((row:any,index:number)=><tr key={`${row.type}-${row.id}-${index}`} className="border-b border-[var(--border-subtle)]"><td className="p-4">{row.date}</td><td className="capitalize">{String(row.type).replace('_',' ')}</td><td>{row.reference??'—'}</td><td>{row.debit}</td><td>{row.credit}</td><td className="capitalize">{row.status}</td></tr>)}</tbody></table></div></Card>
  </div></AppShell>;
}
