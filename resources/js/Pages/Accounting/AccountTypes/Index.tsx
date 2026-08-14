import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';

interface AccountType { id:number; name:string; classification:string; normal_balance:string; accounts_count:number }

export default function AccountTypes({ types }: { types: AccountType[] }) {
  const [form, setForm] = useState({ name:'', classification:'asset', normal_balance:'debit' });
  const submit = (e:React.FormEvent) => { e.preventDefault(); router.post('/accounting/types', form, { onSuccess:()=>setForm({name:'', classification:'asset', normal_balance:'debit'}) }); };
  return <AppShell><Head title="Accounting — Account Types" /><div className="max-w-6xl mx-auto space-y-6 pb-12">
    <div><h1 className="text-2xl font-bold text-[var(--text-primary)]">Account Types</h1><p className="text-xs text-[var(--text-secondary)] mt-1">Control financial classifications and normal debit/credit behavior.</p></div>
    <Card level={0} className="p-5"><form onSubmit={submit} className="grid sm:grid-cols-4 gap-3"><input required placeholder="Type name" value={form.name} onChange={e=>setForm({...form,name:e.target.value})} className="input-shell"/><select value={form.classification} onChange={e=>setForm({...form,classification:e.target.value})} className="input-shell"><option value="asset">Asset</option><option value="liability">Liability</option><option value="equity">Equity</option><option value="income">Income</option><option value="expense">Expense</option></select><select value={form.normal_balance} onChange={e=>setForm({...form,normal_balance:e.target.value})} className="input-shell"><option value="debit">Debit</option><option value="credit">Credit</option></select><Button type="submit" variant="primary">Add Type</Button></form></Card>
    <Card level={0} className="overflow-hidden"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Name</th><th>Classification</th><th>Normal Balance</th><th>Accounts</th><th className="pr-4">Action</th></tr></thead><tbody>{types.map(t=><tr key={t.id} className="border-b border-[var(--border-subtle)]"><td className="p-4 font-medium">{t.name}</td><td className="capitalize">{t.classification}</td><td className="capitalize">{t.normal_balance}</td><td>{t.accounts_count}</td><td className="pr-4"><button className="text-red-400" onClick={()=>router.delete(`/accounting/account-types/${t.id}`)}>Delete</button></td></tr>)}</tbody></table></Card>
  </div></AppShell>;
}
