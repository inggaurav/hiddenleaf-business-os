import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, Plus, Trash2 } from 'lucide-react';

export default function SalesProposalCreate() {
  const { customers = [] } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    customer_id: customers[0]?.id || '',
    issue_date: new Date().toISOString().split('T')[0],
    total_amount: 1500,
    notes: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/sales-proposals');
  };

  return (
    <AppShell title="Create Sales Proposal">
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title="Create Sales Proposal"
          description="Draft commercial terms, estimated deliverables, and customer scope."
          actions={
            <Link href="/sales-proposals">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Proposals
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <Input
              label="Customer / Prospect Identifier"
              placeholder="e.g. Acme Global"
              value={data.customer_id}
              onChange={(e) => setData('customer_id', e.target.value)}
              error={errors.customer_id}
              required
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Proposal Date"
                type="date"
                value={data.issue_date}
                onChange={(e) => setData('issue_date', e.target.value)}
                required
              />
              <Input
                label="Estimated Value ($)"
                type="number"
                step="0.01"
                value={data.total_amount}
                onChange={(e) => setData('total_amount', parseFloat(e.target.value) || 0)}
                required
              />
            </div>

            <Textarea
              label="Scope & Proposal Terms"
              placeholder="Outline project milestones, scope specifications, and terms of engagement..."
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/sales-proposals">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Create Proposal
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
