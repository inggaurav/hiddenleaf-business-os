import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function PurchaseReturnCreate() {
  const { data, setData, post, processing, errors } = useForm({
    vendor_id: '',
    purchase_invoice_id: '',
    total_amount: 100,
    reason: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/purchase-returns');
  };

  return (
    <AppShell title="Record Purchase Return">
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title="Record Purchase Return"
          description="Log inventory returned to vendor and generate debit note credit."
          actions={
            <Link href="/purchase-returns">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <Input
              label="Vendor Identifier"
              placeholder="e.g. Supplier Corp"
              value={data.vendor_id}
              onChange={(e) => setData('vendor_id', e.target.value)}
              error={errors.vendor_id}
              required
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Original Purchase Invoice #"
                placeholder="e.g. 1024"
                value={data.purchase_invoice_id}
                onChange={(e) => setData('purchase_invoice_id', e.target.value)}
              />
              <Input
                label="Return Credit Amount ($)"
                type="number"
                step="0.01"
                value={data.total_amount}
                onChange={(e) => setData('total_amount', parseFloat(e.target.value) || 0)}
                required
              />
            </div>

            <Textarea
              label="Reason for Return"
              placeholder="Defective batch, shipment discrepancy, specification mismatch..."
              value={data.reason}
              onChange={(e) => setData('reason', e.target.value)}
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/purchase-returns">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Log Return
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
