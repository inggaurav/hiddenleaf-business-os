import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { FileText, Plus, Eye, Edit, Trash2 } from 'lucide-react';

export default function PurchaseInvoicesIndex() {
  const { purchaseInvoices = [] } = usePage<any>().props;

  const handleDelete = (id: number) => {
    if (confirm('Delete this vendor purchase invoice?')) {
      router.delete(`/purchase-invoices/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'invoice_id',
      header: 'Purchase #',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-semibold text-emerald-300">
          #{row.invoice_id || row.id}
        </span>
      ),
    },
    {
      key: 'vendor',
      header: 'Vendor / Supplier',
      sortable: true,
      render: (row) => (
        <span className="font-semibold text-white">
          {row.vendor?.name || row.vendor_id || 'Global Supplier'}
        </span>
      ),
    },
    {
      key: 'total_amount',
      header: 'Amount Payable',
      sortable: true,
      render: (row) => (
        <span className="font-bold text-white tabular-nums">
          ${parseFloat(row.total_amount || 0).toFixed(2)}
        </span>
      ),
    },
    {
      key: 'issue_date',
      header: 'Invoice Date',
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
          <Link href={`/purchase-invoices/${row.id}`}>
            <Button variant="ghost" size="sm" icon={<Eye className="w-3.5 h-3.5" />}>
              View
            </Button>
          </Link>
          <Link href={`/purchase-invoices/${row.id}/edit`}>
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
    <AppShell title="Purchase Invoices">
      <div className="space-y-6">
        <SectionHeader
          title="Purchase Invoices & Procurement"
          description="Log vendor supplier bills, procurement expenditures, and receiving inventory ledger entries."
          badge={<Badge variant="success" size="sm">Procurement</Badge>}
          actions={
            <Link href="/purchase-invoices/create">
              <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                Create Purchase Invoice
              </Button>
            </Link>
          }
        />

        <DataTable
          columns={columns}
          data={purchaseInvoices}
          searchPlaceholder="Search vendor purchases..."
          searchKeys={['invoice_id', 'status', 'total_amount']}
          emptyTitle="No purchase invoices"
          emptyDescription="Record supplier invoices for inventory procurement."
        />
      </div>
    </AppShell>
  );
}
