import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';

interface BankAccount {
  id: number;
  account_type_id: number;
  code: string;
  name: string;
  currency: string;
  bank_name?: string | null;
  account_holder?: string | null;
  account_number?: string | null;
  branch_name?: string | null;
  iban?: string | null;
  swift_code?: string | null;
  opening_balance?: string | number;
  is_active: boolean;
}

interface Props {
  bankAccounts: { data: BankAccount[] };
  accountTypes: Array<{ id: number; name: string }>;
  editing?: BankAccount | null;
}

const emptyForm = {
  account_type_id: '', code: '', name: '', currency: 'USD', bank_name: '', account_holder: '',
  account_number: '', branch_name: '', iban: '', swift_code: '', opening_balance: '0.00', is_active: true,
};

export default function BankAccounts({ bankAccounts, accountTypes, editing }: Props) {
  const initial = useMemo(() => editing ? {
    account_type_id: String(editing.account_type_id), code: editing.code, name: editing.name, currency: editing.currency,
    bank_name: editing.bank_name ?? '', account_holder: editing.account_holder ?? '', account_number: editing.account_number ?? '',
    branch_name: editing.branch_name ?? '', iban: editing.iban ?? '', swift_code: editing.swift_code ?? '',
    opening_balance: String(editing.opening_balance ?? '0.00'), is_active: Boolean(editing.is_active),
  } : emptyForm, [editing]);
  const [form, setForm] = useState(initial);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editing) router.put(`/accounting/bank-accounts/${editing.id}`, form);
    else router.post('/accounting/bank-accounts', form, { onSuccess: () => setForm(emptyForm) });
  };

  return <AppShell>
    <Head title="Accounting — Bank Accounts" />
    <div className="max-w-7xl mx-auto space-y-6 pb-12">
      <div><h1 className="text-2xl font-bold text-[var(--text-primary)]">Bank Accounts</h1><p className="text-xs text-[var(--text-secondary)] mt-1">Manage cash and bank ledger identities, account metadata and opening balances.</p></div>
      <Card level={0} className="p-5">
        <form onSubmit={submit} className="grid md:grid-cols-3 gap-3">
          <select required value={form.account_type_id} onChange={e => setForm({...form, account_type_id:e.target.value})} className="input-shell"><option value="">Account type</option>{accountTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}</select>
          <input required placeholder="Ledger code" value={form.code} onChange={e => setForm({...form, code:e.target.value})} className="input-shell" />
          <input required placeholder="Account name" value={form.name} onChange={e => setForm({...form, name:e.target.value})} className="input-shell" />
          <input placeholder="Bank name" value={form.bank_name} onChange={e => setForm({...form, bank_name:e.target.value})} className="input-shell" />
          <input placeholder="Account holder" value={form.account_holder} onChange={e => setForm({...form, account_holder:e.target.value})} className="input-shell" />
          <input placeholder="Account number" value={form.account_number} onChange={e => setForm({...form, account_number:e.target.value})} className="input-shell" />
          <input placeholder="IBAN" value={form.iban} onChange={e => setForm({...form, iban:e.target.value})} className="input-shell" />
          <input placeholder="SWIFT / BIC" value={form.swift_code} onChange={e => setForm({...form, swift_code:e.target.value})} className="input-shell" />
          <div className="flex gap-2"><input required maxLength={3} placeholder="USD" value={form.currency} onChange={e => setForm({...form, currency:e.target.value.toUpperCase()})} className="input-shell w-24" /><input type="number" step="0.01" value={form.opening_balance} disabled={Boolean(editing)} onChange={e => setForm({...form, opening_balance:e.target.value})} className="input-shell flex-1" /></div>
          <div className="md:col-span-3 flex justify-end"><Button type="submit" variant="primary">{editing ? 'Update Bank Account' : 'Create Bank Account'}</Button></div>
        </form>
      </Card>
      <Card level={0} className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Code</th><th>Account</th><th>Bank</th><th>Number</th><th>Currency</th><th>Status</th><th className="pr-4">Actions</th></tr></thead><tbody>{bankAccounts.data.map(a => <tr key={a.id} className="border-b border-[var(--border-subtle)]"><td className="p-4">{a.code}</td><td>{a.name}</td><td>{a.bank_name || '—'}</td><td>{a.account_number || '—'}</td><td>{a.currency}</td><td>{a.is_active ? 'Active' : 'Inactive'}</td><td className="pr-4 space-x-2"><button onClick={() => router.get(`/accounting/bank-accounts/${a.id}/edit`)} className="text-[var(--accent)]">Edit</button><button onClick={() => router.delete(`/accounting/bank-accounts/${a.id}`)} className="text-red-400">Delete</button></td></tr>)}</tbody></table></div></Card>
    </div>
  </AppShell>;
}
