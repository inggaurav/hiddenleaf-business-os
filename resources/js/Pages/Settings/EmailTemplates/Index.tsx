import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Mail, Edit } from 'lucide-react';

export default function EmailTemplatesIndex() {
  const { templates = [] } = usePage<any>().props;

  const columns: Column<any>[] = [
    {
      key: 'name',
      header: 'Template Event',
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <div className="p-1.5 rounded-lg bg-violet-600/10 border border-violet-500/20 text-violet-400">
            <Mail className="w-4 h-4" />
          </div>
          <div>
            <span className="font-semibold text-white block">{row.name}</span>
            <span className="text-[11px] text-gray-400 font-mono">{row.slug || row.name.toLowerCase().replace(/\s+/g, '-')}</span>
          </div>
        </div>
      ),
    },
    {
      key: 'module',
      header: 'Module / Status',
      render: (row) => <div className="flex gap-2"><Badge size="sm">{row.module || 'general'}</Badge><Badge size="sm" variant={row.is_enabled === false ? 'neutral' : 'success'}>{row.is_enabled === false ? 'Disabled' : 'Enabled'}</Badge></div>,
    },
    {
      key: 'subject',
      header: 'Default Subject',
      sortable: true,
      render: (row) => <span className="text-xs text-gray-300">{row.subject || 'Standard Notification'}</span>,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <Link href={`/email-templates/${row.id}`}>
          <Button variant="ghost" size="sm" icon={<Edit className="w-3.5 h-3.5" />}>
            Customize
          </Button>
        </Link>
      ),
    },
  ];

  return (
    <AppShell title="Email Templates">
      <div className="space-y-6">
        <SectionHeader
          title="Transactional Email Templates"
          description="Customize transactional mail copy, placeholders, and multi-language template variations."
          badge={<Badge variant="purple" size="sm">Email Engine</Badge>}
        />

        <DataTable
          columns={columns}
          data={templates}
          searchPlaceholder="Search email templates..."
          searchKeys={['name', 'subject']}
          emptyTitle="No email templates found"
          emptyDescription="Transactional email templates will be listed here."
        />
      </div>
    </AppShell>
  );
}
