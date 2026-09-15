import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  TrendingUp,
  TrendingDown,
  Users,
  FolderKanban,
  Target,
  AlertTriangle,
  CheckCircle,
  Info,
  DollarSign,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SimpleBarChart } from '@/Components/UI/Charts';
import { formatINR, formatINRShort, formatNumber } from '@/lib/format';
import type { OverviewData, QuickInsight, TopCustomer, RecentTransaction } from './types';

interface Props {
  workspace: { id: number; name?: string };
  overview: OverviewData;
}

function InsightIcon({ type }: { type: QuickInsight['type'] }) {
  switch (type) {
    case 'critical':
      return <AlertTriangle className="h-4 w-4 text-red-400" />;
    case 'warning':
      return <AlertTriangle className="h-4 w-4 text-amber-400" />;
    case 'positive':
      return <CheckCircle className="h-4 w-4 text-emerald-400" />;
    default:
      return <Info className="h-4 w-4 text-sky-400" />;
  }
}

function insightBg(type: QuickInsight['type']): string {
  switch (type) {
    case 'critical':
      return 'border-red-500/30 bg-red-500/5';
    case 'warning':
      return 'border-amber-500/30 bg-amber-500/5';
    case 'positive':
      return 'border-emerald-500/30 bg-emerald-500/5';
    default:
      return 'border-sky-500/30 bg-sky-500/5';
  }
}

export default function ExecutiveDashboard({ workspace, overview }: Props) {
  const { kpi_cards: kpi, quick_insights, module_summaries, top_customers, recent_transactions } = overview;

  return (
    <AppShell>
      <Head title="Executive Dashboard — Smart Analytics" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Executive Overview</h1>
            <p className="mt-0.5 text-sm text-[var(--text-secondary)]">
              Cross-module business intelligence at a glance
            </p>
          </div>
          <div className="flex gap-2">
            <Link href="/smart-analytics/financial">
              <button className="rounded-lg bg-[var(--surface-3)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
                Financial
              </button>
            </Link>
            <Link href="/smart-analytics/team">
              <button className="rounded-lg bg-[var(--surface-3)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
                Team
              </button>
            </Link>
            <Link href="/smart-analytics/sales">
              <button className="rounded-lg bg-[var(--surface-3)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
                Sales
              </button>
            </Link>
            <Link href="/smart-analytics/operations">
              <button className="rounded-lg bg-[var(--surface-3)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
                Operations
              </button>
            </Link>
          </div>
        </div>

        {/* KPI Cards */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {/* Revenue */}
          <Card>
            <div className="flex items-center justify-between">
              <DollarSign className="h-5 w-5 text-emerald-400" />
              {kpi.revenue.growth >= 0 ? (
                <TrendingUp className="h-4 w-4 text-emerald-400" />
              ) : (
                <TrendingDown className="h-4 w-4 text-red-400" />
              )}
            </div>
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(kpi.revenue.current)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Monthly Revenue</p>
            <p className={`mt-1 text-xs font-medium ${kpi.revenue.growth >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
              {kpi.revenue.growth >= 0 ? '+' : ''}{kpi.revenue.growth}% vs last month
            </p>
          </Card>

          {/* Net Profit */}
          <Card>
            <div className="flex items-center justify-between">
              <TrendingUp className="h-5 w-5 text-sky-400" />
              <span className="text-xs font-medium text-[var(--text-tertiary)]">{kpi.profit.margin}% margin</span>
            </div>
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(kpi.profit.net)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Net Profit</p>
          </Card>

          {/* Employees */}
          <Card>
            <Users className="h-5 w-5 text-indigo-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(kpi.employees.active)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Active Employees</p>
            {kpi.employees.new_hires > 0 && (
              <p className="mt-1 text-xs text-emerald-400">+{kpi.employees.new_hires} new this month</p>
            )}
          </Card>

          {/* Projects */}
          <Card>
            <FolderKanban className="h-5 w-5 text-amber-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatNumber(kpi.projects.active)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Active Projects</p>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{kpi.projects.completed} completed</p>
          </Card>

          {/* Pipeline */}
          <Card>
            <Target className="h-5 w-5 text-purple-400" />
            <p className="mt-2 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(kpi.sales_pipeline.pipeline_value)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Pipeline Value</p>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">
              {kpi.sales_pipeline.active_leads} leads · {kpi.sales_pipeline.conversion_rate}% conv.
            </p>
          </Card>
        </div>

        {/* Quick Insights + Revenue Trend */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Quick Insights */}
          <Card padded={false}>
            <CardHeader title="Quick Insights" />
            <CardBody>
              <div className="space-y-2">
                {quick_insights.length === 0 && (
                  <p className="text-xs text-[var(--text-tertiary)]">All systems nominal — no alerts.</p>
                )}
                {quick_insights.map((insight, i) => (
                  <div key={i} className={`flex items-start gap-3 rounded-lg border p-3 ${insightBg(insight.type)}`}>
                    <InsightIcon type={insight.type} />
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{insight.title}</p>
                      <p className="text-xs text-[var(--text-secondary)]">{insight.message}</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>

          {/* Revenue Trend */}
          <Card padded={false}>
            <CardHeader title="Revenue Trend (6 Months)" />
            <CardBody>
              <SimpleBarChart
                data={kpi.revenue.trend}
                primaryLabel="Revenue"
                primaryColor="bg-emerald-500"
                height={180}
                emptyMessage="No revenue data for recent months."
              />
            </CardBody>
          </Card>
        </div>

        {/* Top Customers + Recent Transactions */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Top Customers */}
          <Card padded={false}>
            <CardHeader title="Top Customers" />
            <CardBody>
              <div className="space-y-2">
                {(top_customers ?? []).length === 0 && (
                  <p className="text-xs text-[var(--text-tertiary)]">No customer data available.</p>
                )}
                {(top_customers ?? []).map((c: TopCustomer) => (
                  <div key={c.id} className="flex items-center justify-between rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-2)] p-3">
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{c.name}</p>
                      <p className="text-xs text-[var(--text-tertiary)]">{c.email}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-[var(--text-primary)]">{formatINR(c.balance)}</p>
                      <Badge variant={c.status === 'Active' ? 'success' : 'neutral'}>{c.status}</Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>

          {/* Recent Transactions */}
          <Card padded={false}>
            <CardHeader title="Recent Transactions" />
            <CardBody>
              <div className="space-y-2">
                {(recent_transactions ?? []).length === 0 && (
                  <p className="text-xs text-[var(--text-tertiary)]">No recent transactions found.</p>
                )}
                {(recent_transactions ?? []).map((t: RecentTransaction) => (
                  <div key={`${t.type}-${t.id}`} className="flex items-center justify-between rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-2)] p-3">
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{t.reference}</p>
                      <p className="text-xs text-[var(--text-tertiary)]">{t.date}</p>
                    </div>
                    <div className="text-right">
                      <p className={`text-sm font-semibold ${t.type === 'Revenue' ? 'text-emerald-400' : 'text-red-400'}`}>
                        {t.type === 'Revenue' ? '+' : '-'}{formatINR(t.amount)}
                      </p>
                      <Badge variant={t.type === 'Revenue' ? 'success' : 'danger'}>{t.type}</Badge>
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
