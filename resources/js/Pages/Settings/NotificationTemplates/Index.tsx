import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Bell, Edit } from 'lucide-react';

export default function NotificationTemplatesIndex() {
  const { templates = [] } = usePage<any>().props;

  const columns: Column<any>[] = [
    {
      key: 'name',
      header: 'Event Trigger',
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <div className="p-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400">
            <Bell className="w-4 h-4" />
          </div>
          <div>
            <span className="font-semibold text-white block">{row.name}</span>
            <span className="text-[11px] text-gray-400 font-mono">{row.type || 'system-event'}</span>
          </div>
        </div>
      ),
    },
    {
      key: 'content',
      header: 'Notification Text',
      sortable: true,
      render: (row) => <span className="text-xs text-gray-300">{row.content || 'New system event recorded.'}</span>,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <Link href={`/notification-templates/${row.id}`}>
          <Button variant="ghost" size="sm" icon={<Edit className="w-3.5 h-3.5" />}>
            Edit
          </Button>
        </Link>
      ),
    },
  ];

  return (
    <AppShell title="Notification Templates">
      <div className="space-y-6">
        <SectionHeader
          title="In-App & Push Notification Templates"
          description="Customize short notification summaries, team alert feeds, and web push payloads."
          badge={<Badge variant="warning" size="sm">Alert Engine</Badge>}
        />

        <DataTable
          columns={columns}
          data={templates}
          searchPlaceholder="Search notification triggers..."
          searchKeys={['name', 'content']}
          emptyTitle="No notification templates found"
          emptyDescription="Real-time alert templates will appear here."
        />
      </div>
    </AppShell>
  );
}
