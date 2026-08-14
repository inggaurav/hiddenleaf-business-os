import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Edit, RefreshCw, Printer, FileText } from 'lucide-react';

export default function SalesProposalShow() {
  const { proposal } = usePage<any>().props;

  const handleConvert = () => {
    if (confirm('Convert this proposal into an active sales invoice?')) {
      router.post(`/sales-proposals/${proposal.id}/convert`);
    }
  };

  return (
    <AppShell title={`Proposal #${proposal?.proposal_id || proposal?.id}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title={`Sales Proposal #${proposal?.proposal_id || proposal?.id}`}
          description={`Drafted for ${proposal?.customer?.name || proposal?.customer_id || 'Prospect'}`}
          badge={<StatusBadge status={proposal?.status || 'Draft'} />}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/sales-proposals">
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back
                </Button>
              </Link>
              {proposal?.status?.toLowerCase() !== 'converted' && (
                <Button
                  variant="intelligence"
                  size="sm"
                  icon={<RefreshCw className="w-4 h-4" />}
                  onClick={handleConvert}
                >
                  Convert to Invoice
                </Button>
              )}
              <Link href={`/sales-proposals/${proposal?.id}/edit`}>
                <Button variant="secondary" size="sm" icon={<Edit className="w-4 h-4" />}>
                  Edit
                </Button>
              </Link>
            </div>
          }
        />

        <Card level={1} className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
            <div>
              <span className="text-xs uppercase font-bold text-gray-400">Proposal ID</span>
              <h2 className="text-2xl font-bold text-white font-mono mt-0.5">#{proposal?.proposal_id || proposal?.id}</h2>
              <span className="text-xs text-gray-400">
                Created: {proposal?.issue_date ? new Date(proposal.issue_date).toLocaleDateString() : 'Today'}
              </span>
            </div>
            <div className="text-left sm:text-right">
              <span className="text-xs uppercase font-bold text-gray-400">Estimated Valuation</span>
              <p className="text-3xl font-extrabold text-cyan-400 tabular-nums">
                ${parseFloat(proposal?.total_amount || 0).toFixed(2)}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
            <div>
              <span className="text-gray-400 block">Prospect / Account</span>
              <span className="text-white font-semibold">{proposal?.customer?.name || proposal?.customer_id || 'Enterprise Client'}</span>
            </div>
            <div>
              <span className="text-gray-400 block">Status</span>
              <StatusBadge status={proposal?.status || 'Draft'} />
            </div>
            <div>
              <span className="text-gray-400 block">Valid Until</span>
              <span className="text-white font-semibold">30 Days from Issue</span>
            </div>
          </div>

          {proposal?.notes && (
            <div className="pt-4 border-t border-white/10 space-y-2">
              <h4 className="text-xs uppercase font-bold text-gray-400">Scope of Work & Notes</h4>
              <p className="text-sm text-gray-200 leading-relaxed bg-white/[0.02] p-4 rounded-xl border border-white/10">
                {proposal.notes}
              </p>
            </div>
          )}
        </Card>
      </div>
    </AppShell>
  );
}
