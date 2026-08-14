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

export default function SalesInvoiceEdit() {
  const { invoice, warehouses = [] } = usePage<any>().props;

  const { data, setData, put, processing, errors } = useForm({
    customer_id: invoice?.customer_id || '',
    warehouse_id: invoice?.warehouse_id || '',
    issue_date: invoice?.issue_date ? invoice.issue_date.split('T')[0] : '',
    due_date: invoice?.due_date ? invoice.due_date.split('T')[0] : '',
    status: invoice?.status || 'Draft',
    total_amount: invoice?.total_amount || 0,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/sales-invoices/${invoice.id}`);
  };

  return (
    <AppShell title={`Edit Invoice #${invoice?.invoice_id || invoice?.id}`}>
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Invoice #${invoice?.invoice_id || invoice?.id}`}
          description="Update invoice status, fulfillment warehouse, and billing due date."
          actions={
            <Link href="/sales-invoices">
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
                label="Customer Identifier"
                value={data.customer_id}
                onChange={(e) => setData('customer_id', e.target.value)}
                error={errors.customer_id}
                required
              />
              <Select
                label="Status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
              >
                <option value="Draft">Draft</option>
                <option value="Sent">Sent</option>
                <option value="Paid">Paid</option>
                <option value="Partially Paid">Partially Paid</option>
                <option value="Cancelled">Cancelled</option>
              </Select>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Issue Date"
                type="date"
                value={data.issue_date}
                onChange={(e) => setData('issue_date', e.target.value)}
              />
              <Input
                label="Due Date"
                type="date"
                value={data.due_date}
                onChange={(e) => setData('due_date', e.target.value)}
              />
            </div>

            <Input
              label="Total Amount ($)"
              type="number"
              step="0.01"
              value={data.total_amount}
              onChange={(e) => setData('total_amount', parseFloat(e.target.value) || 0)}
              required
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/sales-invoices">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Changes
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
