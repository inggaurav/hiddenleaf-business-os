import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function SalesReturnCreate() {
  const { data, setData, post, processing, errors } = useForm({
    customer_id: '',
    sales_invoice_id: '',
    total_amount: 150,
    reason: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/sales-returns');
  };

  return (
    <AppShell title="Issue Sales Return">
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title="Issue Sales Return & Credit"
          description="Log customer return and issue credit balance or refund."
          actions={
            <Link href="/sales-returns">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <Input
              label="Customer Identifier"
              placeholder="e.g. Acme Corp"
              value={data.customer_id}
              onChange={(e) => setData('customer_id', e.target.value)}
              error={errors.customer_id}
              required
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Original Invoice #"
                placeholder="e.g. INV-1002"
                value={data.sales_invoice_id}
                onChange={(e) => setData('sales_invoice_id', e.target.value)}
              />
              <Input
                label="Credit Amount ($)"
                type="number"
                step="0.01"
                value={data.total_amount}
                onChange={(e) => setData('total_amount', parseFloat(e.target.value) || 0)}
                required
              />
            </div>

            <Textarea
              label="Return Reason"
              placeholder="Customer requested return or cancellation..."
              value={data.reason}
              onChange={(e) => setData('reason', e.target.value)}
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/sales-returns">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Issue Credit
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
