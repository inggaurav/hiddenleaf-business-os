import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function PurchaseInvoiceEdit() {
  const { invoice, warehouses = [] } = usePage<any>().props;

  const { data, setData, put, processing, errors } = useForm({
    vendor_id: invoice?.vendor_id || '',
    warehouse_id: invoice?.warehouse_id || '',
    total_amount: invoice?.total_amount || 0,
    status: invoice?.status || 'Draft',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/purchase-invoices/${invoice.id}`);
  };

  return (
    <AppShell title={`Edit Purchase #${invoice?.invoice_id || invoice?.id}`}>
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Purchase Invoice #${invoice?.invoice_id || invoice?.id}`}
          description="Update vendor billing status and payable totals."
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
            <Input
              label="Vendor Identifier"
              value={data.vendor_id}
              onChange={(e) => setData('vendor_id', e.target.value)}
              error={errors.vendor_id}
              required
            />
            <Select
              label="Payment Status"
              value={data.status}
              onChange={(e) => setData('status', e.target.value)}
            >
              <option value="Draft">Draft</option>
              <option value="Approved">Approved</option>
              <option value="Paid">Paid</option>
              <option value="Cancelled">Cancelled</option>
            </Select>

            <Input
              label="Total Payable ($)"
              type="number"
              step="0.01"
              value={data.total_amount}
              onChange={(e) => setData('total_amount', parseFloat(e.target.value) || 0)}
              required
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/purchase-invoices">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Update Invoice
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
