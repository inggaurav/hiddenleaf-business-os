import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';

interface Category { id:number; name:string; description?:string|null; is_active:boolean }
interface Props { type:'revenue'|'expense'; categories:{data:Category[]}; editing?:Category|null }

export default function Categories({type,categories,editing}:Props) {
  const [form,setForm]=useState({name:editing?.name ?? '',description:editing?.description ?? '',is_active:editing?.is_active ?? true});
  useEffect(()=>setForm({name:editing?.name ?? '',description:editing?.description ?? '',is_active:editing?.is_active ?? true}),[editing]);
  const segment=`${type}-categories`;
  const submit=(e:React.FormEvent)=>{e.preventDefault(); if(editing) router.put(`/accounting/${segment}/${editing.id}`,form); else router.post(`/accounting/${segment}`,form,{onSuccess:()=>setForm({name:'',description:'',is_active:true})});};
  return <AppShell><Head title={`Accounting — ${type==='revenue'?'Revenue':'Expense'} Categories`}/><div className="max-w-6xl mx-auto space-y-6 pb-12">
    <div><h1 className="text-2xl font-bold text-[var(--text-primary)]">{type==='revenue'?'Revenue':'Expense'} Categories</h1><p className="text-xs text-[var(--text-secondary)] mt-1">Classify direct {type} transactions without mixing them with the product catalog.</p></div>
    <Card level={0} className="p-5"><form onSubmit={submit} className="grid sm:grid-cols-[1fr_2fr_auto] gap-3"><input required placeholder="Category name" value={form.name} onChange={e=>setForm({...form,name:e.target.value})} className="input-shell"/><input placeholder="Description" value={form.description} onChange={e=>setForm({...form,description:e.target.value})} className="input-shell"/><Button type="submit" variant="primary">{editing?'Update':'Create'}</Button></form></Card>
    <Card level={0} className="overflow-hidden"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Name</th><th>Description</th><th>Status</th><th className="pr-4">Actions</th></tr></thead><tbody>{categories.data.map(c=><tr key={c.id} className="border-b border-[var(--border-subtle)]"><td className="p-4 font-medium">{c.name}</td><td>{c.description || '—'}</td><td>{c.is_active?'Active':'Inactive'}</td><td className="pr-4 space-x-2"><button className="text-[var(--accent)]" onClick={()=>router.get(`/accounting/${segment}/${c.id}/edit`)}>Edit</button><button className="text-red-400" onClick={()=>router.delete(`/accounting/${segment}/${c.id}`)}>Delete</button></td></tr>)}</tbody></table></Card>
  </div></AppShell>;
}
