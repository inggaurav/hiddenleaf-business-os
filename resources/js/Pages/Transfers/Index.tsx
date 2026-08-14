import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowRightLeft, Plus, Eye, Trash2 } from 'lucide-react';

export default function TransfersIndex() {
  const { transfers = [] } = usePage<any>().props;

  const handleDelete = (id: number) => {
    if (confirm('Cancel / delete this stock transfer record?')) {
      router.delete(`/transfers/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'transfer_id',
      header: 'Transfer Ref',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-semibold text-amber-300">
          #{row.transfer_id || row.id}
        </span>
      ),
    },
    {
      key: 'from_warehouse',
      header: 'Source Facility',
      sortable: true,
      render: (row) => (
        <span className="font-semibold text-white">
          {row.from_warehouse?.name || row.from_warehouse_id || 'Origin Hub'}
        </span>
      ),
    },
    {
      key: 'to_warehouse',
      header: 'Destination Facility',
      sortable: true,
      render: (row) => (
        <span className="font-semibold text-violet-300">
          {row.to_warehouse?.name || row.to_warehouse_id || 'Target Hub'}
        </span>
      ),
    },
    {
      key: 'date',
      header: 'Transfer Date',
      sortable: true,
      render: (row) => (
        <span className="text-xs text-gray-400">
          {row.date ? new Date(row.date).toLocaleDateString() : 'Today'}
        </span>
      ),
    },
    {
      key: 'quantity',
      header: 'Units Transferred',
      sortable: true,
      render: (row) => <span className="font-bold text-white tabular-nums">{row.quantity || 1} units</span>,
    },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (row) => <StatusBadge status={row.status || 'Completed'} />,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Link href={`/transfers/${row.id}`}>
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
    <AppShell title="Stock Transfers">
      <div className="space-y-6">
        <SectionHeader
          title="Warehouse Stock Transfers"
          description="Log and trace inventory movements across isolated storage facilities and logistics nodes."
          badge={<Badge variant="warning" size="sm">Logistics</Badge>}
          actions={
            <Link href="/transfers/create">
              <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                Transfer Stock
              </Button>
            </Link>
          }
        />

        <DataTable
          columns={columns}
          data={transfers}
          searchPlaceholder="Search transfer records by warehouse or ID..."
          searchKeys={['transfer_id', 'status']}
          emptyTitle="No stock transfers recorded"
          emptyDescription="Initiate a stock movement between your warehouses."
        />
      </div>
    </AppShell>
  );
}
