import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { DataTable, Column, LaravelPaginator } from '@/Components/UI/DataTable';
import { Badge } from '@/Components/UI/Badge';
import { formatDateTime } from '@/lib/format';

interface AuditLogRecord {
  id: string;
  actor_id?: number | null;
  actor?: { name: string; email: string };
  action: string;
  entity_type?: string;
  entity_id?: string | number;
  ip?: string;
  created_at: string;
}

interface Props {
  logs: LaravelPaginator<AuditLogRecord>;
}

export default function AuditLog({ logs }: Props) {
  const columns: Column<AuditLogRecord>[] = [
    {
      header: 'Timestamp',
      accessorKey: 'created_at',
      render: (row) => (
        <span className="text-xs text-[var(--text-secondary)] tabular-nums">
          {formatDateTime(row.created_at)}
        </span>
      ),
    },
    {
      header: 'Actor',
      render: (row) => (
        <div className="flex flex-col">
          <span className="text-sm font-medium text-[var(--text-primary)]">
            {row.actor?.name || 'System'}
          </span>
          {row.actor?.email && (
            <span className="text-xs text-[var(--text-tertiary)]">{row.actor.email}</span>
          )}
        </div>
      ),
    },
    {
      header: 'Action',
      accessorKey: 'action',
      render: (row) => <Badge variant="neutral">{row.action}</Badge>,
    },
    {
      header: 'Target Entity',
      render: (row) => (
        <span className="text-xs text-[var(--text-secondary)]">
          {row.entity_type ? `${row.entity_type.split('\\').pop()} #${row.entity_id ?? ''}` : '—'}
        </span>
      ),
    },
    {
      header: 'IP Address',
      accessorKey: 'ip',
      render: (row) => (
        <span className="text-xs font-mono text-[var(--text-tertiary)]">
          {row.ip || '—'}
        </span>
      ),
    },
  ];

  return (
    <AppShell title="Audit Log">
      <Head title="Audit Log — Settings" />
      <div className="space-y-6">
        <SectionHeader
          title="Audit Log"
          description="A verifiable activity trail of administrative and operational actions within your organization."
        />
        <DataTable
          data={logs}
          columns={columns}
          keyExtractor={(row) => row.id}
          searchPlaceholder="Search audit trail..."
          emptyTitle="No audit logs recorded"
          emptyDescription="User and system operations will appear here as they occur."
        />
      </div>
    </AppShell>
  );
}
