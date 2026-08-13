import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Building, Plus, Edit, Trash2, ArrowRightLeft, MapPin } from 'lucide-react';

export default function WarehousesIndex() {
  const { warehouses = [] } = usePage<any>().props;

  const handleDelete = (id: number) => {
    if (confirm('Delete this warehouse facility?')) {
      router.delete(`/warehouses/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'name',
      header: 'Warehouse Facility',
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <div className="p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400">
            <Building className="w-4 h-4" />
          </div>
          <div>
            <span className="font-semibold text-white block">{row.name}</span>
            <span className="text-[11px] text-gray-400 font-mono">Code: {row.code || 'HUB'}</span>
          </div>
        </div>
      ),
    },
    {
      key: 'address',
      header: 'Location / Address',
      sortable: true,
      render: (row) => (
        <span className="text-xs text-gray-300 flex items-center gap-1">
          <MapPin className="w-3.5 h-3.5 text-gray-500" />
          {row.address || `${row.city || 'Central Hub'}, ${row.country || 'Global'}`}
        </span>
      ),
    },
    {
      key: 'phone',
      header: 'Contact Phone',
      sortable: true,
      render: (row) => <span className="text-xs text-gray-400">{row.phone || '—'}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (row) => <StatusBadge status={row.status ?? 'Active'} />,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Link href={`/warehouses/${row.id}/edit`}>
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
    <AppShell title="Warehouses">
      <div className="space-y-6">
        <SectionHeader
          title="Warehouses & Inventory Facilities"
          description="Manage physical stock hubs, fulfillment centers, and intra-facility transfer routing."
          badge={<Badge variant="warning" size="sm">Operations</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/transfers">
                <Button variant="secondary" size="sm" icon={<ArrowRightLeft className="w-4 h-4" />}>
                  Stock Transfers
                </Button>
              </Link>
              <Link href="/warehouses/create">
                <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                  Add Warehouse
                </Button>
              </Link>
            </div>
          }
        />

        <DataTable
          columns={columns}
          data={warehouses}
          searchPlaceholder="Search warehouse name, location or code..."
          searchKeys={['name', 'code', 'address', 'city']}
          emptyTitle="No warehouses registered"
          emptyDescription="Create your primary inventory storage warehouse facility."
        />
      </div>
    </AppShell>
  );
}
