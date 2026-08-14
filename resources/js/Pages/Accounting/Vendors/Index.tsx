import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Plus, Mail, Phone, MapPin, Search } from 'lucide-react';

interface Vendor {
  id: number;
  name: string;
  email: string | null;
  contact: string | null;
  tax_number: string | null;
  billing_city: string | null;
  billing_country: string | null;
  balance: number;
  is_active: boolean;
  purchase_invoices_count?: number;
}

interface Props {
  vendors: {
    data: Vendor[];
    total: number;
  };
}

export default function VendorsIndex({ vendors }: Props) {
  const [showModal, setShowModal] = useState(false);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState({
    name: '',
    email: '',
    contact: '',
    tax_number: '',
    billing_name: '',
    billing_country: '',
    billing_city: '',
    billing_phone: '',
    billing_address: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/accounting/vendors', form, {
      onSuccess: () => {
        setShowModal(false);
        setForm({
          name: '',
          email: '',
          contact: '',
          tax_number: '',
          billing_name: '',
          billing_country: '',
          billing_city: '',
          billing_phone: '',
          billing_address: '',
        });
      },
    });
  };

  const filtered = vendors.data.filter(v =>
    v.name.toLowerCase().includes(search.toLowerCase()) ||
    (v.email && v.email.toLowerCase().includes(search.toLowerCase()))
  );

  return (
    <AppShell>
      <Head title="Accounting — Vendors" />

      <div className="space-y-6 max-w-7xl mx-auto pb-12">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-[var(--text-primary)]">
              Supplier & Vendor Directory
            </h1>
            <p className="text-xs text-[var(--text-secondary)] mt-1">
              Manage suppliers, procurement payment terms, and accounts payable balances.
            </p>
          </div>

          <Button
            variant="primary"
            icon={<Plus className="w-4 h-4" />}
            onClick={() => setShowModal(true)}
          >
            Add Vendor
          </Button>
        </div>

        {/* Search and Filters */}
        <div className="flex items-center gap-3">
          <div className="relative flex-1 max-w-md">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-tertiary)]" />
            <input
              type="text"
              placeholder="Search vendors by name or email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-9 pr-4 py-2 text-xs bg-[var(--surface-1)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)] focus:outline-none focus:border-purple-500"
            />
          </div>
        </div>

        {/* Vendor List */}
        <Card level={0} className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-[var(--text-secondary)]">
              <thead className="bg-[var(--surface-2)] text-[var(--text-tertiary)] uppercase font-semibold border-b border-[var(--border-subtle)]">
                <tr>
                  <th className="px-5 py-3">Vendor / Supplier</th>
                  <th className="px-5 py-3">Contact</th>
                  <th className="px-5 py-3">Location</th>
                  <th className="px-5 py-3">Bills</th>
                  <th className="px-5 py-3">Balance</th>
                  <th className="px-5 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-subtle)]">
                {filtered.length > 0 ? (
                  filtered.map((v) => (
                    <tr key={v.id} className="hover:bg-white/[0.02] spring-transition">
                      <td className="px-5 py-3.5 font-medium text-[var(--text-primary)]">
                        <div className="flex items-center gap-2.5">
                          <div className="w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 font-bold text-xs">
                            {v.name.charAt(0)}
                          </div>
                          <div>
                            <div>{v.name}</div>
                            {v.tax_number && <div className="text-[10px] text-[var(--text-tertiary)]">Tax: {v.tax_number}</div>}
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-3.5">
                        {v.email && (
                          <div className="flex items-center gap-1.5 text-[var(--text-primary)]">
                            <Mail className="w-3 h-3 text-[var(--text-tertiary)]" />
                            <span>{v.email}</span>
                          </div>
                        )}
                        {v.contact && (
                          <div className="flex items-center gap-1.5 text-[var(--text-tertiary)] text-[10px] mt-0.5">
                            <Phone className="w-3 h-3" />
                            <span>{v.contact}</span>
                          </div>
                        )}
                      </td>
                      <td className="px-5 py-3.5">
                        {v.billing_city || v.billing_country ? (
                          <div className="flex items-center gap-1.5">
                            <MapPin className="w-3 h-3 text-[var(--text-tertiary)]" />
                            <span>{[v.billing_city, v.billing_country].filter(Boolean).join(', ')}</span>
                          </div>
                        ) : '—'}
                      </td>
                      <td className="px-5 py-3.5">
                        <Badge variant="neutral" size="sm">{v.purchase_invoices_count ?? 0} Bills</Badge>
                      </td>
                      <td className="px-5 py-3.5 font-semibold text-[var(--text-primary)]">
                        ${Number(v.balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-5 py-3.5">
                        <Badge variant={v.is_active ? 'success' : 'neutral'} size="sm">
                          {v.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-5 py-8 text-center text-[var(--text-tertiary)]">
                      No vendors registered in this workspace.
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
          <div className="w-full max-w-lg bg-[var(--surface-1)] border border-[var(--border-subtle)] rounded-2xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
            <h2 className="text-base font-bold text-[var(--text-primary)]">Add New Vendor</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="sm:col-span-2">
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Vendor / Supplier Name *</label>
                  <input
                    required
                    type="text"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Email</label>
                  <input
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Contact Phone</label>
                  <input
                    type="text"
                    value={form.contact}
                    onChange={(e) => setForm({ ...form, contact: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Tax / VAT Number</label>
                  <input
                    type="text"
                    value={form.tax_number}
                    onChange={(e) => setForm({ ...form, tax_number: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">City</label>
                  <input
                    type="text"
                    value={form.billing_city}
                    onChange={(e) => setForm({ ...form, billing_city: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Billing Address</label>
                  <textarea
                    rows={2}
                    value={form.billing_address}
                    onChange={(e) => setForm({ ...form, billing_address: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl text-[var(--text-primary)]"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <Button variant="ghost" size="sm" type="button" onClick={() => setShowModal(false)}>
                  Cancel
                </Button>
                <Button variant="primary" size="sm" type="submit">
                  Save Vendor
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppShell>
  );
}
