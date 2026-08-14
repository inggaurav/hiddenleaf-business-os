import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';

interface Account { id:number; account_type_id:number; parent_id?:number|null; code:string; name:string; currency:string; is_active:boolean; type?:{name:string;classification:string} }
interface Props { account:Account; transactions?:{data:Array<any>}; types?:Array<{id:number;name:string}>; editMode?:boolean }

export default function ShowAccount({ account, transactions, types = [], editMode = false }:Props) {
  const [form,setForm]=useState({account_type_id:String(account.account_type_id),parent_id:account.parent_id?String(account.parent_id):'',code:account.code,name:account.name,currency:account.currency,is_active:Boolean(account.is_active)});
  return <AppShell><Head title={`Account — ${account.name}`} /><div className="max-w-6xl mx-auto space-y-6 pb-12">
    <div className="flex justify-between gap-4"><div><h1 className="text-2xl font-bold text-[var(--text-primary)]">{account.code} · {account.name}</h1><p className="text-xs text-[var(--text-secondary)] mt-1">{account.type?.name ?? 'Ledger account'} · {account.currency}</p></div>{!editMode && <Button variant="secondary" onClick={()=>router.get(`/accounting/accounts/${account.id}/edit`)}>Edit</Button>}</div>
    {editMode && <Card level={0} className="p-5"><form onSubmit={e=>{e.preventDefault();router.put(`/accounting/accounts/${account.id}`,form)}} className="grid sm:grid-cols-2 gap-3"><select value={form.account_type_id} onChange={e=>setForm({...form,account_type_id:e.target.value})} className="input-shell">{types.map(t=><option key={t.id} value={t.id}>{t.name}</option>)}</select><input value={form.code} onChange={e=>setForm({...form,code:e.target.value})} className="input-shell"/><input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} className="input-shell"/><input value={form.currency} maxLength={3} onChange={e=>setForm({...form,currency:e.target.value.toUpperCase()})} className="input-shell"/><div className="sm:col-span-2 flex justify-end"><Button type="submit" variant="primary">Save Account</Button></div></form></Card>}
    <Card level={0} className="overflow-hidden"><div className="p-4 border-b border-[var(--border-subtle)] font-semibold">Journal Activity</div><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Entry</th><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th></tr></thead><tbody>{(transactions?.data ?? []).map((line:any)=><tr key={line.id} className="border-b border-[var(--border-subtle)]"><td className="p-4">{line.entry?.entry_number ?? line.journal_entry_id}</td><td>{line.entry?.entry_date ?? '—'}</td><td>{line.description ?? line.entry?.description ?? '—'}</td><td>{line.debit}</td><td>{line.credit}</td></tr>)}</tbody></table></Card>
  </div></AppShell>;
}
