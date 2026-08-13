import React, { useState } from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, Plus, Trash2, DollarSign } from 'lucide-react';

export default function SalesInvoiceCreate() {
  const { warehouses = [], customers = [] } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    customer_id: customers[0]?.id || '',
    warehouse_id: warehouses[0]?.id || '',
    issue_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 14 * 86400000).toISOString().split('T')[0],
    notes: '',
    items: [
      { description: 'Standard Commercial Service', quantity: 1, unit_price: 150.0 },
    ],
  });

  const handleAddItem = () => {
    setData('items', [
      ...data.items,
      { description: 'Product / Service Item', quantity: 1, unit_price: 0.0 },
    ]);
  };

  const handleRemoveItem = (index: number) => {
    if (data.items.length === 1) return;
    setData('items', data.items.filter((_, i) => i !== index));
  };

  const handleItemChange = (index: number, field: string, val: any) => {
    const updated = [...data.items];
    (updated[index] as any)[field] = val;
    setData('items', updated);
  };

  const totalAmount = data.items.reduce(
    (sum, it) => sum + (parseFloat(String(it.quantity)) || 0) * (parseFloat(String(it.unit_price)) || 0),
    0
  );

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/sales-invoices');
  };

  return (
    <AppShell title="Create Sales Invoice">
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title="New Sales Invoice"
          description="Issue an invoice to a customer, specify line items, taxes, and warehouse inventory origins."
          actions={
            <Link href="/sales-invoices">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Invoices
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-6">
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
                Invoice Details
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Customer / Client Identifier"
                  placeholder="e.g. Acme Corp"
                  value={data.customer_id}
                  onChange={(e) => setData('customer_id', e.target.value)}
                  error={errors.customer_id}
                  required
                />
                {warehouses.length > 0 ? (
                  <Select
                    label="Fulfillment Warehouse"
                    value={data.warehouse_id}
                    onChange={(e) => setData('warehouse_id', e.target.value)}
                  >
                    {warehouses.map((w: any) => (
                      <option key={w.id} value={w.id}>
                        {w.name} ({w.code || 'HUB'})
                      </option>
                    ))}
                  </Select>
                ) : (
                  <Input
                    label="Warehouse Origin"
                    placeholder="Primary Warehouse"
                    value={data.warehouse_id}
                    onChange={(e) => setData('warehouse_id', e.target.value)}
                  />
                )}
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Issue Date"
                  type="date"
                  value={data.issue_date}
                  onChange={(e) => setData('issue_date', e.target.value)}
                  required
                />
                <Input
                  label="Due Date"
                  type="date"
                  value={data.due_date}
                  onChange={(e) => setData('due_date', e.target.value)}
                  required
                />
              </div>
            </div>

            {/* Line Items */}
            <div className="pt-4 border-t border-white/10 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
                  Line Items
                </h3>
                <Button type="button" variant="outline" size="sm" icon={<Plus className="w-3.5 h-3.5" />} onClick={handleAddItem}>
                  Add Item
                </Button>
              </div>

              <div className="space-y-3">
                {data.items.map((item, idx) => {
                  const lineTotal = (parseFloat(String(item.quantity)) || 0) * (parseFloat(String(item.unit_price)) || 0);
                  return (
                    <div key={idx} className="p-3.5 rounded-xl bg-white/[0.02] border border-white/10 grid grid-cols-12 gap-3 items-center">
                      <div className="col-span-12 sm:col-span-6">
                        <Input
                          placeholder="Item description or service..."
                          value={item.description}
                          onChange={(e) => handleItemChange(idx, 'description', e.target.value)}
                          required
                        />
                      </div>
                      <div className="col-span-6 sm:col-span-2">
                        <Input
                          type="number"
                          min={1}
                          placeholder="Qty"
                          value={item.quantity}
                          onChange={(e) => handleItemChange(idx, 'quantity', parseFloat(e.target.value) || 1)}
                          required
                        />
                      </div>
                      <div className="col-span-6 sm:col-span-2">
                        <Input
                          type="number"
                          step="0.01"
                          placeholder="Price"
                          value={item.unit_price}
                          onChange={(e) => handleItemChange(idx, 'unit_price', parseFloat(e.target.value) || 0)}
                          required
                        />
                      </div>
                      <div className="col-span-12 sm:col-span-2 flex items-center justify-between gap-2">
                        <span className="text-xs font-bold text-white tabular-nums">${lineTotal.toFixed(2)}</span>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="text-rose-400"
                          disabled={data.items.length <= 1}
                          onClick={() => handleRemoveItem(idx)}
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </Button>
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* Total Calculation */}
              <div className="p-4 rounded-xl bg-white/[0.03] border border-white/10 flex items-center justify-between text-sm">
                <span className="text-gray-400">Total Invoice Valuation</span>
                <span className="text-xl font-extrabold text-violet-400 tabular-nums">${totalAmount.toFixed(2)}</span>
              </div>
            </div>

            <div className="pt-4 border-t border-white/10">
              <Textarea
                label="Terms & Client Notes"
                placeholder="Payment terms, delivery details, wire transfer notes..."
                value={data.notes}
                onChange={(e) => setData('notes', e.target.value)}
              />
            </div>

            <div className="pt-6 border-t border-white/10 flex items-center justify-end gap-3">
              <Link href="/sales-invoices">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Create Invoice
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
