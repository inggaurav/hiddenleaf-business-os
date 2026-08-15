import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { ModuleSectionActions } from '@/Components/ModuleSectionActions';
import { ModuleLifecycleActions } from '@/Components/ModuleLifecycleActions';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';

function displayValue(value: any): string {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';
  if (typeof value === 'object') {
    if (value.name) return String(value.name);
    return JSON.stringify(value);
  }
  return String(value);
}

export default function ModuleSection() {
  const { module, section, title, description, columns = [], records, breadcrumbs = [], canManage = false, lookups = {} } = usePage<any>().props;
  const rows = records?.data || records || [];
  const [query, setQuery] = React.useState('');

  const filtered = React.useMemo(() => {
    const q = query.toLowerCase().trim();
    if (!q) return rows;
    return rows.filter((row: any) => columns.some((column: string) => displayValue(row?.[column]).toLowerCase().includes(q)));
  }, [rows, columns, query]);

  return (
    <AppShell title={title} breadcrumbs={breadcrumbs}>
      <div className="space-y-5">
        <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <Badge variant="purple" size="sm">{module}</Badge>
              <span className="text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">Workspace Module</span>
              <Badge variant={canManage ? 'success' : 'neutral'} size="sm">{canManage ? 'Manage' : 'View only'}</Badge>
            </div>
            <h1 className="text-xl font-bold text-[var(--text-primary)]">{title}</h1>
            <p className="text-xs text-[var(--text-tertiary)] mt-1 max-w-2xl">{description}</p>
          </div>

          <div className="relative w-full md:w-72">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-tertiary)]" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={`Search ${title.toLowerCase()}...`}
              className="w-full pl-9 pr-3 py-2 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none focus:border-purple-500/50"
            />
          </div>
        </div>

        <ModuleSectionActions module={module} section={section} canManage={canManage} lookups={lookups} />
        <ModuleLifecycleActions module={module} section={section} canManage={canManage} lookups={lookups} />

        <Card level={0} className="overflow-hidden p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead className="bg-white/[0.03] border-b border-[var(--border-subtle)]">
                <tr>
                  {columns.map((column: string) => (
                    <th key={column} className="px-4 py-3 text-left font-semibold text-[var(--text-tertiary)] uppercase tracking-wider whitespace-nowrap">
                      {column.replaceAll('_', ' ')}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-subtle)]">
                {filtered.length ? filtered.map((row: any, index: number) => (
                  <tr key={row.id || index} className="hover:bg-white/[0.025]">
                    {columns.map((column: string) => (
                      <td key={column} className="px-4 py-3 text-[var(--text-secondary)] max-w-xs truncate" title={displayValue(row?.[column])}>
                        {displayValue(row?.[column])}
                      </td>
                    ))}
                  </tr>
                )) : (
                  <tr>
                    <td colSpan={Math.max(columns.length, 1)} className="px-4 py-14 text-center text-[var(--text-tertiary)]">
                      No records found in this workspace.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {(records?.prev_page_url || records?.next_page_url) && (
            <div className="flex items-center justify-between px-4 py-3 border-t border-[var(--border-subtle)] bg-white/[0.015]">
              <span className="text-[11px] text-[var(--text-tertiary)]">
                Page {records.current_page || 1} of {records.last_page || 1} · {records.total || rows.length} records
              </span>
              <div className="flex items-center gap-2">
                {records.prev_page_url ? <Link href={records.prev_page_url} className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[var(--border-subtle)] hover:bg-white/5"><ChevronLeft className="w-3.5 h-3.5" /> Previous</Link> : null}
                {records.next_page_url ? <Link href={records.next_page_url} className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[var(--border-subtle)] hover:bg-white/5">Next <ChevronRight className="w-3.5 h-3.5" /></Link> : null}
              </div>
            </div>
          )}
        </Card>
      </div>
    </AppShell>
  );
}
