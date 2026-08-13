import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Headphones, Plus, Eye, Trash2, FolderTree } from 'lucide-react';

export default function HelpdeskIndex() {
  const { tickets = [] } = usePage<any>().props;

  const handleDelete = (id: number) => {
    if (confirm('Delete this support ticket?')) {
      router.delete(`/helpdesk-tickets/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'ticket_id',
      header: 'Ticket #',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-semibold text-rose-300">
          #{row.ticket_id || row.id}
        </span>
      ),
    },
    {
      key: 'title',
      header: 'Subject / Title',
      sortable: true,
      render: (row) => (
        <div>
          <span className="font-semibold text-white block">{row.title}</span>
          <span className="text-[11px] text-gray-400 truncate max-w-xs block">{row.description}</span>
        </div>
      ),
    },
    {
      key: 'name',
      header: 'Requester',
      sortable: true,
      render: (row) => (
        <div>
          <span className="font-medium text-gray-200 block">{row.name || 'Customer'}</span>
          <span className="text-[11px] text-gray-400">{row.email}</span>
        </div>
      ),
    },
    {
      key: 'category',
      header: 'Category',
      sortable: true,
      render: (row) => (
        <Badge variant="purple" size="sm">
          {row.category?.name || 'General Support'}
        </Badge>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (row) => <StatusBadge status={row.status || 'Open'} />,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Link href={`/helpdesk-tickets/${row.id}`}>
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
    <AppShell title="Helpdesk Support">
      <div className="space-y-6">
        <SectionHeader
          title="Customer Helpdesk & Support Queue"
          description="Resolve incoming customer support requests, manage SLA response targets, and categorize issues."
          badge={<Badge variant="danger" size="sm">Helpdesk Queue</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/helpdesk-categories">
                <Button variant="secondary" size="sm" icon={<FolderTree className="w-4 h-4" />}>
                  Categories
                </Button>
              </Link>
              <Link href="/helpdesk-tickets/create">
                <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                  Open Ticket
                </Button>
              </Link>
            </div>
          }
        />

        <DataTable
          columns={columns}
          data={tickets}
          searchPlaceholder="Search tickets by subject, requester or email..."
          searchKeys={['title', 'name', 'email', 'ticket_id']}
          emptyTitle="No support tickets"
          emptyDescription="Customer inquiries and tickets will appear here."
        />
      </div>
    </AppShell>
  );
}
