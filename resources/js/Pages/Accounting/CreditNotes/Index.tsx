import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Plus, Calendar, FileText } from 'lucide-react';

interface CreditNote {
  id: number;
  amount: number;
  date: string;
  description: string | null;
  status: string;
  customer?: { id: number; name: string };
  invoice?: { id: number; invoice_id: string; total_amount: number };
}

interface Props {
  creditNotes: {
    data: CreditNote[];
    total: number;
  };
  customers: Array<{ id: number; name: string }>;
  invoices: Array<{ id: number; invoice_id: string; total_amount: number }>;
}

export default function CreditNotesIndex({ creditNotes, customers, invoices }: Props) {
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({
    customer_id: '',
    invoice_id: '',
    amount: '',
    date: new Date().toISOString().split('T')[0],
    description: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/accounting/credit-notes', form, {
      onSuccess: () => {
        setShowModal(false);
        setForm({
          customer_id: '',
          invoice_id: '',
          amount: '',
          date: new Date().toISOString().split('T')[0],
          description: '',
        });
      },
    });
  };

  return (
    <AppShell>
      <Head title="Accounting — Credit Notes" />

      <div className="space-y-6 max-w-7xl mx-auto pb-12">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-[var(--text-primary)]">
              Credit Notes
            </h1>
            <p className="text-xs text-[var(--text-secondary)] mt-1">
              Issue commercial credit to customers and apply balance adjustments against sales invoices.
            </p>
          </div>

          <Button
            variant="primary"
            icon={<Plus className="w-4 h-4" />}
            onClick={() => setShowModal(true)}
          >
            Issue Credit Note
          </Button>
        </div>

        <Card level={0} className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-[var(--text-secondary)]">
              <thead className="bg-[var(--surface-2)] text-[var(--text-tertiary)] uppercase font-semibold border-b border-[var(--border-subtle)]">
                <tr>
                  <th className="px-5 py-3">Date</th>
                  <th className="px-5 py-3">Customer</th>
                  <th className="px-5 py-3">Invoice</th>
                  <th className="px-5 py-3">Reason / Description</th>
                  <th className="px-5 py-3">Amount</th>
                  <th className="px-5 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-subtle)]">
                {creditNotes.data.length > 0 ? (
                  creditNotes.data.map((cn) => (
                    <tr key={cn.id} className="hover:bg-white/[0.02] spring-transition">
                      <td className="px-5 py-3.5 font-medium text-[var(--text-primary)]">
                        <div className="flex items-center gap-1.5">
                          <Calendar className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
                          <span>{cn.date}</span>
                        </div>
                      </td>
                      <td className="px-5 py-3.5 font-medium text-[var(--text-primary)]">
                        {cn.customer?.name ?? '—'}
                      </td>
                      <td className="px-5 py-3.5">
                        {cn.invoice?.invoice_id ? (
                          <Badge variant="brand" size="sm">{cn.invoice.invoice_id}</Badge>
                        ) : '—'}
                      </td>
                      <td className="px-5 py-3.5">
                        {cn.description ?? 'Credit adjustment'}
                      </td>
                      <td className="px-5 py-3.5 font-bold text-amber-400">
                        ${Number(cn.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-5 py-3.5">
                        <Badge variant="success" size="sm">{cn.status}</Badge>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-5 py-8 text-center text-[var(--text-tertiary)]">
                      No credit notes issued in this workspace.
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
            <h2 className="text-base font-bold text-[var(--text-primary)]">Issue New Credit Note</h2>
            <form onSubmit={handleSubmit} className="space-y-3">
              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Customer</label>
                <select
                  value={form.customer_id}
                  onChange={(e) => setForm({ ...form, customer_id: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                >
                  <option value="">Select Customer (Optional)</option>
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Apply to Sales Invoice</label>
                <select
                  value={form.invoice_id}
                  onChange={(e) => setForm({ ...form, invoice_id: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                >
                  <option value="">Select Invoice (Optional)</option>
                  {invoices.map((inv) => (
                    <option key={inv.id} value={inv.id}>{inv.invoice_id} (${inv.total_amount})</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
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
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Reason / Description</label>
                <textarea
                  rows={2}
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  placeholder="e.g. Return credit or pricing adjustment"
                  className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <Button variant="ghost" size="sm" type="button" onClick={() => setShowModal(false)}>
                  Cancel
                </Button>
                <Button variant="primary" size="sm" type="submit">
                  Issue Credit Note
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppShell>
  );
}
