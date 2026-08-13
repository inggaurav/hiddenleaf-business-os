import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { FileText, Plus, Eye, Edit, Trash2, ArrowRight, RefreshCw } from 'lucide-react';

export default function SalesProposalsIndex() {
  const { proposals = [] } = usePage<any>().props;

  const handleConvert = (id: number) => {
    if (confirm('Convert this proposal into an active sales invoice?')) {
      router.post(`/sales-proposals/${id}/convert`);
    }
  };

  const handleDelete = (id: number) => {
    if (confirm('Delete this proposal?')) {
      router.delete(`/sales-proposals/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'proposal_id',
      header: 'Proposal #',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-semibold text-cyan-300">
          #{row.proposal_id || row.id}
        </span>
      ),
    },
    {
      key: 'customer',
      header: 'Prospect / Client',
      sortable: true,
      render: (row) => (
        <div>
          <span className="font-semibold text-white block">{row.customer?.name || row.customer_id || 'Prospect Corp'}</span>
          <span className="text-[11px] text-gray-400">{row.customer?.email || '—'}</span>
        </div>
      ),
    },
    {
      key: 'total_amount',
      header: 'Estimated Value',
      sortable: true,
      render: (row) => (
        <span className="font-bold text-white tabular-nums">
          ${parseFloat(row.total_amount || 0).toFixed(2)}
        </span>
      ),
    },
    {
      key: 'issue_date',
      header: 'Date',
      sortable: true,
      render: (row) => (
        <span className="text-xs text-gray-400">
          {row.issue_date ? new Date(row.issue_date).toLocaleDateString() : '—'}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (row) => <StatusBadge status={row.status || 'Draft'} />,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          {row.status?.toLowerCase() !== 'converted' && (
            <Button
              variant="intelligence"
              size="sm"
              icon={<RefreshCw className="w-3.5 h-3.5" />}
              onClick={() => handleConvert(row.id)}
            >
              Convert to Invoice
            </Button>
          )}
          <Link href={`/sales-proposals/${row.id}`}>
            <Button variant="ghost" size="sm" icon={<Eye className="w-3.5 h-3.5" />}>
              View
            </Button>
          </Link>
          <Link href={`/sales-proposals/${row.id}/edit`}>
            <Button variant="ghost" size="sm" icon={<Edit className="w-3.5 h-3.5" />}>
              Edit
            </Button>
          </Link>
          <Button
            variant="ghost"
            size="sm"
            className="text-rose-400 hover:text-rose-300"
            icon={<Trash2 className="w-3.5 h-3.5" />}
            onClick={() => handleDelete(row.id)}
          >
            Delete
          </Button>
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Sales Proposals">
      <div className="space-y-6">
        <SectionHeader
          title="Sales Proposals & Quotes"
          description="Create commercial quotes, estimate project valuations, and convert won proposals into sales invoices with one click."
          badge={<Badge variant="cyan" size="sm">Pipeline</Badge>}
          actions={
            <Link href="/sales-proposals/create">
              <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                Create Proposal
              </Button>
            </Link>
          }
        />

        <DataTable
          columns={columns}
          data={proposals}
          searchPlaceholder="Search proposals by reference, prospect or status..."
          searchKeys={['proposal_id', 'status', 'total_amount']}
          emptyTitle="No proposals created"
          emptyDescription="Draft your first sales proposal or quotation."
        />
      </div>
    </AppShell>
  );
}
