import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowRightLeft, Plus, Eye, Trash2 } from 'lucide-react';

export default function PurchaseReturnsIndex() {
  const { purchaseReturns = [] } = usePage<any>().props;

  const handleDelete = (id: number) => {
    if (confirm('Delete this purchase return record?')) {
      router.delete(`/purchase-returns/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'return_id',
      header: 'Return #',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-semibold text-rose-300">
          #{row.return_id || row.id}
        </span>
      ),
    },
    {
      key: 'vendor',
      header: 'Vendor',
      sortable: true,
      render: (row) => <span className="font-semibold text-white">{row.vendor?.name || row.vendor_id || 'Supplier'}</span>,
    },
    {
      key: 'total_amount',
      header: 'Credit Value',
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
          <Link href={`/purchase-returns/${row.id}`}>
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
    <AppShell title="Purchase Returns">
      <div className="space-y-6">
        <SectionHeader
          title="Purchase Returns"
          description="Track supplier debit notes, returned inventory items, and vendor credits."
          badge={<Badge variant="danger" size="sm">Debit Notes</Badge>}
          actions={
            <Link href="/purchase-returns/create">
              <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                Record Return
              </Button>
            </Link>
          }
        />

        <DataTable
          columns={columns}
          data={purchaseReturns}
          searchPlaceholder="Search purchase returns..."
          searchKeys={['return_id', 'status']}
          emptyTitle="No purchase returns logged"
          emptyDescription="Record items returned back to vendors."
        />
      </div>
    </AppShell>
  );
}
