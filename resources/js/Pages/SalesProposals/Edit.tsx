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

export default function SalesProposalEdit() {
  const { proposal } = usePage<any>().props;

  const { data, setData, put, processing, errors } = useForm({
    customer_id: proposal?.customer_id || '',
    issue_date: proposal?.issue_date ? proposal.issue_date.split('T')[0] : '',
    total_amount: proposal?.total_amount || 0,
    status: proposal?.status || 'Draft',
    notes: proposal?.notes || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/sales-proposals/${proposal.id}`);
  };

  return (
    <AppShell title={`Edit Proposal #${proposal?.proposal_id || proposal?.id}`}>
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Proposal #${proposal?.proposal_id || proposal?.id}`}
          description="Update proposal parameters, status, and valuation."
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
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Customer Identifier"
                value={data.customer_id}
                onChange={(e) => setData('customer_id', e.target.value)}
                error={errors.customer_id}
                required
              />
              <Select
                label="Proposal Status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
              >
                <option value="Draft">Draft</option>
                <option value="Sent">Sent</option>
                <option value="Accepted">Accepted</option>
                <option value="Declined">Declined</option>
                <option value="Converted">Converted</option>
              </Select>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Proposal Date"
                type="date"
                value={data.issue_date}
                onChange={(e) => setData('issue_date', e.target.value)}
              />
              <Input
                label="Total Estimated Value ($)"
                type="number"
                step="0.01"
                value={data.total_amount}
                onChange={(e) => setData('total_amount', parseFloat(e.target.value) || 0)}
              />
            </div>

            <Textarea
              label="Proposal Terms & Scope"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/sales-proposals">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Update Proposal
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
