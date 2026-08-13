import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowRightLeft, Plus, Eye, Trash2 } from 'lucide-react';

export default function SalesReturnsIndex() {
  const { salesReturns = [] } = usePage<any>().props;

  const handleDelete = (id: number) => {
    if (confirm('Delete this sales return credit record?')) {
      router.delete(`/sales-returns/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'return_id',
      header: 'Credit Note #',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-semibold text-amber-300">
          #{row.return_id || row.id}
        </span>
      ),
    },
    {
      key: 'customer',
      header: 'Customer',
      sortable: true,
      render: (row) => <span className="font-semibold text-white">{row.customer?.name || row.customer_id || 'Client'}</span>,
    },
    {
      key: 'total_amount',
      header: 'Refund / Credit',
      sortable: true,
      render: (row) => (
        <span className="font-bold text-white tabular-nums">
          ${parseFloat(row.total_amount || 0).toFixed(2)}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (row) => <StatusBadge status={row.status || 'Processed'} />,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Link href={`/sales-returns/${row.id}`}>
            <Button variant="ghost" size="sm" icon={<Eye className="w-3.5 h-3.5" />}>
              View
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
    <AppShell title="Sales Returns">
      <div className="space-y-6">
        <SectionHeader
          title="Sales Returns & Credit Notes"
          description="Process customer returned items, refund authorizations, and credit ledger deductions."
          badge={<Badge variant="warning" size="sm">Credit Notes</Badge>}
          actions={
            <Link href="/sales-returns/create">
              <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                Record Sales Return
              </Button>
            </Link>
          }
        />

        <DataTable
          columns={columns}
          data={salesReturns}
          searchPlaceholder="Search customer sales returns..."
          searchKeys={['return_id', 'status']}
          emptyTitle="No sales returns"
          emptyDescription="Customer credit notes will appear here."
        />
      </div>
    </AppShell>
  );
}
