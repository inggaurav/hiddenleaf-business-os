import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Plus, Calendar, CheckCircle2 } from 'lucide-react';

interface Payment {
  id: number;
  amount: number;
  payment_date: string;
  payment_method: string;
  reference: string | null;
  description: string | null;
  vendor?: { id: number; name: string };
  purchase_invoice?: { id: number; invoice_id: string; total_amount: number };
  account?: { id: number; name: string };
}

interface Props {
  payments: {
    data: Payment[];
    total: number;
  };
  vendors: Array<{ id: number; name: string }>;
  bills: Array<{ id: number; invoice_id: string; total_amount: number }>;
  accounts: Array<{ id: number; name: string; code: string }>;
}

export default function VendorPayments({ payments, vendors, bills, accounts }: Props) {
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({
    vendor_id: '',
    purchase_invoice_id: '',
    account_id: accounts[0]?.id ? String(accounts[0].id) : '',
    amount: '',
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'bank_transfer',
    reference: '',
    description: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/accounting/vendor-payments', form, {
      onSuccess: () => {
        setShowModal(false);
        setForm({
          vendor_id: '',
          purchase_invoice_id: '',
          account_id: accounts[0]?.id ? String(accounts[0].id) : '',
          amount: '',
          payment_date: new Date().toISOString().split('T')[0],
          payment_method: 'bank_transfer',
          reference: '',
          description: '',
        });
      },
    });
  };

  return (
    <AppShell>
      <Head title="Accounting — Vendor Payments" />

      <div className="space-y-6 max-w-7xl mx-auto pb-12">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-[var(--text-primary)]">
              Vendor Payments & Disbursements
            </h1>
            <p className="text-xs text-[var(--text-secondary)] mt-1">
              Disburse payments for vendor bills, reduce accounts payable, and synchronize cash ledger balances.
            </p>
          </div>

          <Button
            variant="primary"
            icon={<Plus className="w-4 h-4" />}
            onClick={() => setShowModal(true)}
          >
            Record Payment
          </Button>
        </div>

        <Card level={0} className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-[var(--text-secondary)]">
              <thead className="bg-[var(--surface-2)] text-[var(--text-tertiary)] uppercase font-semibold border-b border-[var(--border-subtle)]">
                <tr>
                  <th className="px-5 py-3">Date</th>
                  <th className="px-5 py-3">Vendor / Supplier</th>
                  <th className="px-5 py-3">Bill / Purchase Invoice</th>
                  <th className="px-5 py-3">Disbursement Account</th>
                  <th className="px-5 py-3">Method</th>
                  <th className="px-5 py-3">Amount</th>
                  <th className="px-5 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-subtle)]">
                {payments.data.length > 0 ? (
                  payments.data.map((p) => (
                    <tr key={p.id} className="hover:bg-white/[0.02] spring-transition">
                      <td className="px-5 py-3.5 font-medium text-[var(--text-primary)]">
                        <div className="flex items-center gap-1.5">
                          <Calendar className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
                          <span>{p.payment_date}</span>
                        </div>
                      </td>
                      <td className="px-5 py-3.5 font-medium text-[var(--text-primary)]">
                        {p.vendor?.name ?? 'Direct Payment'}
                      </td>
                      <td className="px-5 py-3.5">
                        {p.purchase_invoice?.invoice_id ? (
                          <Badge variant="brand" size="sm">{p.purchase_invoice.invoice_id}</Badge>
                        ) : '—'}
                      </td>
                      <td className="px-5 py-3.5">
                        {p.account?.name ?? 'General Cash'}
                      </td>
                      <td className="px-5 py-3.5 capitalize">
                        {p.payment_method.replace('_', ' ')}
                      </td>
                      <td className="px-5 py-3.5 font-bold text-amber-400">
                        -${Number(p.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-1 text-emerald-400">
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          <span className="font-semibold text-[11px]">Disbursed</span>
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={7} className="px-5 py-8 text-center text-[var(--text-tertiary)]">
                      No vendor disbursements recorded yet.
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
            <h2 className="text-base font-bold text-[var(--text-primary)]">Record Vendor Disbursement</h2>
            <form onSubmit={handleSubmit} className="space-y-3">
              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Vendor / Supplier</label>
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

              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Apply to Purchase Bill</label>
                <select
                  value={form.purchase_invoice_id}
                  onChange={(e) => setForm({ ...form, purchase_invoice_id: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                >
                  <option value="">Select Bill (Optional)</option>
                  {bills.map((bill) => (
                    <option key={bill.id} value={bill.id}>{bill.invoice_id} (${bill.total_amount})</option>
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
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Payment Date *</label>
                  <input
                    required
                    type="date"
                    value={form.payment_date}
                    onChange={(e) => setForm({ ...form, payment_date: e.target.value })}
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
                    <option value="cheque">Cheque</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Reference / Transaction ID</label>
                <input
                  type="text"
                  value={form.reference}
                  onChange={(e) => setForm({ ...form, reference: e.target.value })}
                  placeholder="e.g. TXN-44912"
                  className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <Button variant="ghost" size="sm" type="button" onClick={() => setShowModal(false)}>
                  Cancel
                </Button>
                <Button variant="primary" size="sm" type="submit">
                  Confirm Disbursement
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppShell>
  );
}
