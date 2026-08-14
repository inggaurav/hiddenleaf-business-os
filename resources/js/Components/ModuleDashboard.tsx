import React from 'react';
import { usePage } from '@inertiajs/react';
import { Activity, BarChart3, Boxes, CircleDollarSign } from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { MetricCard } from '@/Components/UI/Card';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { SectionHeader } from '@/Components/UI/SectionHeader';

type RecordRow = Record<string, any>;

interface CollectionConfig {
  key: string;
  title: string;
  columns: string[];
}

interface ModuleDashboardProps {
  title: string;
  description: string;
  metricLabels: Record<string, string>;
  collections: CollectionConfig[];
}

function humanize(value: string): string {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function displayValue(value: unknown): React.ReactNode {
  if (value === null || value === undefined || value === '') return <span className="text-[var(--text-tertiary)]">—</span>;
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';
  if (typeof value === 'object') {
    const record = value as RecordRow;
    return record.name ?? record.title ?? record.code ?? '—';
  }
  return String(value);
}

function metricValue(key: string, value: unknown): string | number {
  const numeric = Number(value ?? 0);
  if (/revenue|value|payroll|cost|sales|purchases|transfers/.test(key)) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number.isFinite(numeric) ? numeric : 0);
  }
  if (/rate/.test(key)) return `${Number.isFinite(numeric) ? numeric : 0}%`;
  return Number.isFinite(numeric) ? numeric : String(value ?? '—');
}

export default function ModuleDashboard({ title, description, metricLabels, collections }: ModuleDashboardProps) {
  const props = usePage<RecordRow>().props;
  const metrics = (props.metrics ?? {}) as Record<string, unknown>;
  const icons = [Activity, BarChart3, CircleDollarSign, Boxes];

  return (
    <AppShell title={title}>
      <div className="space-y-6">
        <SectionHeader title={title} description={description} />

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {Object.entries(metricLabels).map(([key, label], index) => {
            const Icon = icons[index % icons.length];
            return <MetricCard key={key} title={label} value={metricValue(key, metrics[key])} icon={<Icon className="h-5 w-5 text-violet-400" />} />;
          })}
        </div>

        {collections.map((collection) => {
          const columns: Column<RecordRow>[] = collection.columns.map((key) => ({
            key,
            header: humanize(key),
            render: (row) => <span className="text-xs text-[var(--text-secondary)]">{displayValue(row[key])}</span>,
          }));

          return (
            <section key={collection.key} className="space-y-3" aria-labelledby={`${collection.key}-heading`}>
              <h2 id={`${collection.key}-heading`} className="text-sm font-semibold text-[var(--text-primary)]">{collection.title}</h2>
              <DataTable
                data={(props[collection.key] as any) ?? []}
                columns={columns}
                emptyTitle={`No ${collection.title.toLowerCase()}`}
                emptyDescription="This workspace has no records in this section yet."
              />
            </section>
          );
        })}
      </div>
    </AppShell>
  );
}
