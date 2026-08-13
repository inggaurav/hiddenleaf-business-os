import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function PurchaseInvoiceCreate() {
  const { warehouses = [] } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    vendor_id: '',
    warehouse_id: warehouses[0]?.id || '',
    issue_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
    total_amount: 500,
    notes: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/purchase-invoices');
  };

  return (
    <AppShell title="Create Purchase Invoice">
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title="New Vendor Purchase Invoice"
          description="Log supplier billing data and link inventory to fulfillment storage hubs."
          actions={
            <Link href="/purchase-invoices">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Invoices
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Vendor / Supplier Name"
                placeholder="e.g. Acme Supplier Corp"
                value={data.vendor_id}
                onChange={(e) => setData('vendor_id', e.target.value)}
                error={errors.vendor_id}
                required
              />
              {warehouses.length > 0 ? (
                <Select
                  label="Receiving Warehouse"
                  value={data.warehouse_id}
                  onChange={(e) => setData('warehouse_id', e.target.value)}
                >
                  {warehouses.map((w: any) => (
                    <option key={w.id} value={w.id}>
                      {w.name}
                    </option>
                  ))}
                </Select>
              ) : (
                <Input
                  label="Warehouse Destination"
                  placeholder="Primary Warehouse"
                  value={data.warehouse_id}
                  onChange={(e) => setData('warehouse_id', e.target.value)}
                />
              )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Invoice Date"
                type="date"
                value={data.issue_date}
                onChange={(e) => setData('issue_date', e.target.value)}
                required
              />
              <Input
                label="Total Payable ($)"
                type="number"
                step="0.01"
                value={data.total_amount}
                onChange={(e) => setData('total_amount', parseFloat(e.target.value) || 0)}
                required
              />
            </div>

            <Textarea
              label="Purchase Notes & PO Reference"
              placeholder="PO-2026-99, vendor shipping terms..."
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/purchase-invoices">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Invoice
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
