import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ShieldCheck, ArrowLeft, Globe, Clock } from 'lucide-react';

export default function LoginHistory() {
  const { loginDetails = [] } = usePage<any>().props;

  const columns: Column<any>[] = [
    {
      key: 'user',
      header: 'User Account',
      sortable: true,
      render: (row) => (
        <div>
          <span className="font-semibold text-white block">{row.user?.name || 'Unknown User'}</span>
          <span className="text-[11px] text-gray-400">{row.user?.email || '—'}</span>
        </div>
      ),
    },
    {
      key: 'ip',
      header: 'IP Address',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs text-violet-300">
          {row.ip || row.ip_address || '127.0.0.1'}
        </span>
      ),
    },
    {
      key: 'date',
      header: 'Timestamp',
      sortable: true,
      render: (row) => (
        <span className="text-xs text-gray-300">
          {new Date(row.date || row.created_at).toLocaleString()}
        </span>
      ),
    },
    {
      key: 'details',
      header: 'Client / User Agent',
      render: (row) => (
        <span className="text-[11px] text-gray-400 truncate max-w-xs block" title={row.details}>
          {row.details || 'Web Client'}
        </span>
      ),
    },
  ];

  return (
    <AppShell title="Security & Login History">
      <div className="space-y-6">
        <SectionHeader
          title="Security Login History"
          description="Forensic records of all successful authentication sessions, IP origins, and user agents."
          badge={<Badge variant="purple" size="sm">Audit Trail</Badge>}
          actions={
            <Link href="/users">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Users
              </Button>
            </Link>
          }
        />

        <DataTable
          columns={columns}
          data={loginDetails}
          searchPlaceholder="Search IP address or user name..."
          searchKeys={['ip', 'details']}
          emptyTitle="No login history recorded"
          emptyDescription="Authentication attempts will be logged automatically."
        />
      </div>
    </AppShell>
  );
}
