import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Target, TrendingUp, ArrowLeft, DollarSign, Users } from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SimpleBarChart, ProgressDistribution } from '@/Components/UI/Charts';
import { formatINR, formatINRShort, formatNumber } from '@/lib/format';
import type { SalesData, SalesDeal, SalesLead } from './types';

interface Props {
  workspace: { id: number; name?: string };
  sales: SalesData;
}

export default function SalesAnalytics({ workspace, sales }: Props) {
  const { kpis, pipeline_funnel, monthly_trend, recent_deals, recent_leads } = sales;

  return (
    <AppShell>
      <Head title="Sales & Customer Analytics — Smart Analytics" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Link href="/smart-analytics/dashboard">
            <button className="rounded-lg bg-[var(--surface-3)] p-2 border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
              <ArrowLeft className="h-4 w-4 text-[var(--text-primary)]" />
            </button>
          </Link>
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Sales & Customer Analytics</h1>
            <p className="mt-0.5 text-sm text-[var(--text-secondary)]">Deals, leads, and pipeline performance</p>
          </div>
        </div>

        {/* KPI Row */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Card>
            <Target className="h-5 w-5 text-purple-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(kpis.pipeline_value)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Pipeline Value</p>
          </Card>
          <Card>
            <DollarSign className="h-5 w-5 text-emerald-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(kpis.won_value)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Won Value</p>
          </Card>
          <Card>
            <TrendingUp className="h-5 w-5 text-sky-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{kpis.win_rate}%</p>
            <p className="text-xs text-[var(--text-tertiary)]">Win Rate</p>
          </Card>
          <Card>
            <Users className="h-5 w-5 text-amber-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(kpis.open_leads)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Open Leads</p>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{kpis.conversion_rate}% conversion</p>
          </Card>
          <Card>
            <DollarSign className="h-5 w-5 text-indigo-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(kpis.avg_deal_size)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Avg Deal Size</p>
          </Card>
        </div>

        {/* Pipeline Funnel + Monthly Won Trend */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card padded={false}>
            <CardHeader title="Pipeline Funnel" />
            <CardBody>
              {pipeline_funnel.length === 0 ? (
                <p className="text-xs text-[var(--text-tertiary)]">No pipeline stages configured.</p>
              ) : (
                <ProgressDistribution items={pipeline_funnel} />
              )}
            </CardBody>
          </Card>

          <Card padded={false}>
            <CardHeader title="Monthly Won Revenue (6 Months)" />
            <CardBody>
              <SimpleBarChart
                data={monthly_trend}
                primaryLabel="Won Value"
                primaryColor="bg-emerald-500"
                height={200}
                emptyMessage="No deal data for recent months."
              />
            </CardBody>
          </Card>
        </div>

        {/* Recent Deals + Recent Leads */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card padded={false}>
            <CardHeader title="Recent Deals" />
            <CardBody>
              <div className="space-y-2">
                {(recent_deals ?? []).length === 0 && <p className="text-xs text-[var(--text-tertiary)]">No deals yet.</p>}
                {(recent_deals ?? []).map((d: SalesDeal) => (
                  <div key={d.id} className="flex items-center justify-between rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-2)] p-3">
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{d.name}</p>
                      <p className="text-xs text-[var(--text-tertiary)]">Close: {d.expected_close_date} · {d.created_at}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-[var(--text-primary)]">{formatINR(d.value)}</p>
                      <Badge variant={d.status === 'won' ? 'success' : d.status === 'lost' ? 'danger' : 'neutral'}>{d.status}</Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>

          <Card padded={false}>
            <CardHeader title="Recent Leads" />
            <CardBody>
              <div className="space-y-2">
                {(recent_leads ?? []).length === 0 && <p className="text-xs text-[var(--text-tertiary)]">No leads yet.</p>}
                {(recent_leads ?? []).map((l: SalesLead) => (
                  <div key={l.id} className="flex items-center justify-between rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-2)] p-3">
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{l.name}</p>
                      <p className="text-xs text-[var(--text-tertiary)]">{l.email} · {l.created_at}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-[var(--text-primary)]">{formatINR(l.estimated_value)}</p>
                      <Badge variant={l.status === 'converted' ? 'success' : l.status === 'lost' ? 'danger' : 'neutral'}>{l.status}</Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
