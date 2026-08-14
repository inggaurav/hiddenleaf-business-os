import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Plus, Calendar } from 'lucide-react';

interface Expense {
  id: number;
  amount: number;
  date: string;
  payment_method: string;
  reference: string | null;
  description: string | null;
  vendor?: { id: number; name: string };
  account?: { id: number; name: string };
}

interface Props {
  expenses: {
    data: Expense[];
    total: number;
  };
  vendors: Array<{ id: number; name: string }>;
  accounts: Array<{ id: number; name: string; code: string }>;
}

export default function ExpensesIndex({ expenses, vendors, accounts }: Props) {
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({
    vendor_id: '',
    account_id: accounts[0]?.id ? String(accounts[0].id) : '',
    amount: '',
    date: new Date().toISOString().split('T')[0],
    payment_method: 'bank_transfer',
    reference: '',
    description: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/accounting/expenses', form, {
      onSuccess: () => {
        setShowModal(false);
        setForm({
          vendor_id: '',
          account_id: accounts[0]?.id ? String(accounts[0].id) : '',
          amount: '',
          date: new Date().toISOString().split('T')[0],
          payment_method: 'bank_transfer',
          reference: '',
          description: '',
        });
      },
    });
  };

  return (
    <AppShell>
      <Head title="Accounting — Expenses" />

      <div className="space-y-6 max-w-7xl mx-auto pb-12">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-[var(--text-primary)]">
              Direct Workspace Expenses
            </h1>
            <p className="text-xs text-[var(--text-secondary)] mt-1">
              Record office, travel, utility, and miscellaneous operational expenditures with real-time double-entry posting.
            </p>
          </div>

          <Button
            variant="primary"
            icon={<Plus className="w-4 h-4" />}
            onClick={() => setShowModal(true)}
          >
            Add Expense
          </Button>
        </div>

        <Card level={0} className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-[var(--text-secondary)]">
              <thead className="bg-[var(--surface-2)] text-[var(--text-tertiary)] uppercase font-semibold border-b border-[var(--border-subtle)]">
                <tr>
                  <th className="px-5 py-3">Date</th>
                  <th className="px-5 py-3">Reference / Description</th>
                  <th className="px-5 py-3">Vendor / Payee</th>
                  <th className="px-5 py-3">Paid From Account</th>
                  <th className="px-5 py-3">Method</th>
                  <th className="px-5 py-3">Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-subtle)]">
                {expenses.data.length > 0 ? (
                  expenses.data.map((e) => (
                    <tr key={e.id} className="hover:bg-white/[0.02] spring-transition">
                      <td className="px-5 py-3.5 font-medium text-[var(--text-primary)]">
                        <div className="flex items-center gap-1.5">
                          <Calendar className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
                          <span>{e.date}</span>
                        </div>
                      </td>
                      <td className="px-5 py-3.5">
                        <div className="font-semibold text-[var(--text-primary)]">{e.reference ?? 'Direct Expense'}</div>
                        {e.description && <div className="text-[11px] text-[var(--text-tertiary)]">{e.description}</div>}
                      </td>
                      <td className="px-5 py-3.5">
                        {e.vendor?.name ?? '—'}
                      </td>
                      <td className="px-5 py-3.5">
                        {e.account?.name ?? 'General Ledger'}
                      </td>
                      <td className="px-5 py-3.5 capitalize">
                        {e.payment_method.replace('_', ' ')}
                      </td>
                      <td className="px-5 py-3.5 font-bold text-amber-400">
                        -${Number(e.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-5 py-8 text-center text-[var(--text-tertiary)]">
                      No direct expenses recorded yet.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
          <div className="w-full max-w-lg bg-[var(--surface-1)] border border-[var(--border-subtle)] rounded-2xl p-6 shadow-2xl space-y-4">
            <h2 className="text-base font-bold text-[var(--text-primary)]">Record Direct Expense</h2>
            <form onSubmit={handleSubmit} className="space-y-3">
              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Vendor / Payee</label>
                <select
                  value={form.vendor_id}
                  onChange={(e) => setForm({ ...form, vendor_id: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                >
                  <option value="">Select Vendor (Optional)</option>
                  {vendors.map((v) => (
                    <option key={v.id} value={v.id}>{v.name}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Paid From Account *</label>
                  <select
                    required
                    value={form.account_id}
                    onChange={(e) => setForm({ ...form, account_id: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  >
                    {accounts.map((a) => (
                      <option key={a.id} value={a.id}>{a.code} - {a.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Amount ($) *</label>
                  <input
                    required
                    type="number"
                    step="0.01"
                    min="0.01"
                    value={form.amount}
                    onChange={(e) => setForm({ ...form, amount: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Date *</label>
                  <input
                    required
                    type="date"
                    value={form.date}
                    onChange={(e) => setForm({ ...form, date: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Payment Method</label>
                  <select
                    value={form.payment_method}
                    onChange={(e) => setForm({ ...form, payment_method: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  >
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="cash">Cash</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Reference / Note</label>
                <input
                  type="text"
                  value={form.reference}
                  onChange={(e) => setForm({ ...form, reference: e.target.value })}
                  placeholder="e.g. Cloud Hosting / Utilities"
                  className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <Button variant="ghost" size="sm" type="button" onClick={() => setShowModal(false)}>
                  Cancel
                </Button>
                <Button variant="primary" size="sm" type="submit">
                  Save Expense
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppShell>
  );
}
