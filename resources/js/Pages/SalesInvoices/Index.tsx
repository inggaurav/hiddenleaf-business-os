import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { FileText, Plus, Eye, Edit, Trash2, ArrowRight } from 'lucide-react';

export default function SalesInvoicesIndex() {
  const { invoices = [] } = usePage<any>().props;

  const handleDelete = (id: number) => {
    if (confirm('Delete this sales invoice?')) {
      router.delete(`/sales-invoices/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'invoice_id',
      header: 'Invoice #',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-semibold text-violet-300">
          #{row.invoice_id || row.id}
        </span>
      ),
    },
    {
      key: 'customer',
      header: 'Customer / Client',
      sortable: true,
      render: (row) => (
        <div>
          <span className="font-semibold text-white block">{row.customer?.name ?? (row.customer_id ? `Customer #${row.customer_id}` : '—')}</span>
          <span className="text-[11px] text-gray-400">{row.customer?.email || '—'}</span>
        </div>
      ),
    },
    {
      key: 'warehouse',
      header: 'Warehouse',
      sortable: true,
      render: (row) => (
        <span className="text-xs text-gray-300">
          {row.warehouse?.name ?? '—'}
        </span>
      ),
    },
    {
      key: 'total_amount',
      header: 'Total Amount',
      sortable: true,
      render: (row) => (
        <span className="font-bold text-white tabular-nums">
          ${parseFloat(row.total_amount || 0).toFixed(2)}
        </span>
      ),
    },
    {
      key: 'due_date',
      header: 'Due Date',
      sortable: true,
      render: (row) => (
        <span className="text-xs text-gray-400">
          {row.due_date ? new Date(row.due_date).toLocaleDateString() : '—'}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (row) => <StatusBadge status={String(row.status ?? 'unknown')} />,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Link href={`/sales-invoices/${row.id}`}>
            <Button variant="ghost" size="sm" icon={<Eye className="w-3.5 h-3.5" />}>
              View
            </Button>
          </Link>
          <Link href={`/sales-invoices/${row.id}/edit`}>
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
    <AppShell title="Sales Invoices">
      <div className="space-y-6">
        <SectionHeader
          title="Sales Invoices"
          description="Issue, manage, and track commercial invoices, multi-warehouse stock allocations, and client receivables."
          badge={<Badge variant="purple" size="sm">Sales Engine</Badge>}
          actions={
            <Link href="/sales-invoices/create">
              <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                Create Invoice
              </Button>
            </Link>
          }
        />

        <DataTable
          columns={columns}
          data={invoices}
          searchPlaceholder="Search invoices by reference, client or warehouse..."
          searchKeys={['invoice_id', 'status', 'total_amount']}
          emptyTitle="No invoices created"
          emptyDescription="Create your first sales invoice to bill customers."
        />
      </div>
    </AppShell>
  );
}
