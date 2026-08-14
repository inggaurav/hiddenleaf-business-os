import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  FileText,
  FileSpreadsheet,
  DollarSign,
  TrendingUp,
  RotateCcw,
  CheckCircle2,
  Clock,
  Plus,
  ArrowRight,
  Percent,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { SimpleBarChart } from '@/Components/UI/Charts';

interface SalesStats {
  total_invoices: number;
  draft_invoices: number;
  posted_invoices: number;
  paid_invoices: number;
  total_sales_amount: number;
  outstanding_receivables: number;
  total_proposals: number;
  accepted_proposals: number;
  pending_proposals: number;
  rejected_proposals: number;
  conversion_rate: number;
  total_returns: number;
  returns_amount: number;
}

interface MonthlySalesItem {
  month: string;
  sales: number;
}

interface InvoiceItem {
  id: number;
  invoice_number: string;
  customer_name: string;
  grand_total: number;
  status: string;
  issue_date: string;
}

interface ProposalItem {
  id: number;
  proposal_number: string;
  customer_name: string;
  grand_total: number;
  status: string;
  issue_date: string;
}

interface SalesDashboardProps {
  stats: SalesStats;
  monthlySales: MonthlySalesItem[];
  recentInvoices: InvoiceItem[];
  recentProposals: ProposalItem[];
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function SalesDashboard({
  stats,
  monthlySales = [],
  recentInvoices = [],
  recentProposals = [],
}: SalesDashboardProps) {
  const chartData = monthlySales.map((item) => ({
    label: item.month,
    value: item.sales,
    formattedValue: formatCurrency(item.sales),
  }));

  return (
    <AppShell title="Sales Dashboard">
      <Head title="Sales & Invoicing Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="Sales & Invoicing Dashboard"
            description="Accounts receivable lifecycle, proposal conversion funnel, sales revenue volume, and billing status."
          />
          <div className="flex items-center gap-3">
            <Link
              href="/sales-proposals"
              className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-2)] px-4 py-2.5 text-xs font-semibold text-[var(--text-primary)] border border-[var(--border-subtle)] hover:bg-[var(--surface-3)] transition"
            >
              <FileSpreadsheet className="h-4 w-4 text-[var(--text-secondary)]" />
              Proposals
            </Link>
            <Link
              href="/sales-invoices"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <Plus className="h-4 w-4" />
              Sales Invoices
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Sales Invoiced"
            value={formatCurrency(stats.total_sales_amount)}
            icon={<DollarSign className="h-5 w-5 text-emerald-400" />}
            subtitle={`${stats.total_invoices} total invoices issued`}
          />
          <MetricCard
            title="Outstanding Receivables"
            value={formatCurrency(stats.outstanding_receivables)}
            icon={<Clock className="h-5 w-5 text-amber-400" />}
            trend={{
              value: stats.outstanding_receivables > 0 ? 'Pending collection' : 'Fully settled',
              neutral: true,
            }}
          />
          <MetricCard
            title="Paid Invoices"
            value={stats.paid_invoices}
            icon={<CheckCircle2 className="h-5 w-5 text-emerald-400" />}
            subtitle={`${stats.posted_invoices} posted, ${stats.draft_invoices} draft`}
          />
          <MetricCard
            title="Proposal Conversion"
            value={`${stats.conversion_rate}%`}
            icon={<Percent className="h-5 w-5 text-violet-400" />}
            trend={{
              value: `${stats.accepted_proposals} / ${stats.total_proposals} accepted`,
              positive: stats.conversion_rate >= 25,
            }}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Proposals"
            value={stats.total_proposals}
            icon={<FileSpreadsheet className="h-5 w-5 text-indigo-400" />}
            subtitle={`${stats.pending_proposals} pending client review`}
          />
          <MetricCard
            title="Sales Returns"
            value={stats.total_returns}
            icon={<RotateCcw className="h-5 w-5 text-rose-400" />}
            subtitle={formatCurrency(stats.returns_amount)}
          />
          <MetricCard
            title="Active Invoices"
            value={stats.posted_invoices}
            icon={<FileText className="h-5 w-5 text-sky-400" />}
            subtitle="Posted and awaiting payment"
          />
          <MetricCard
            title="Draft Invoices"
            value={stats.draft_invoices}
            icon={<Clock className="h-5 w-5 text-[var(--text-tertiary)]" />}
            subtitle="Unposted draft documents"
          />
        </div>

        {/* Monthly Sales Revenue Trend */}
        <Card level={0} className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Monthly Invoiced Revenue Velocity
              </h3>
              <p className="text-xs text-[var(--text-tertiary)]">
                Sales invoiced across the last 6 billing cycles
              </p>
            </div>
            <Badge variant="success">6 Months</Badge>
          </div>
          <SimpleBarChart
            data={chartData}
            primaryLabel="Invoiced Sales"
            primaryColor="bg-emerald-500"
            emptyMessage="No sales invoices posted in the last 6 months."
          />
        </Card>

        {/* Recent Invoices & Proposals Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Recent Invoices */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Sales Invoices
              </h3>
              <Link
                href="/sales-invoices"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentInvoices.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No sales invoices recorded.
                </div>
              ) : (
                recentInvoices.map((inv) => (
                  <div key={inv.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {inv.invoice_number}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {inv.customer_name}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {inv.issue_date}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        {formatCurrency(inv.grand_total)}
                      </div>
                      <Badge
                        variant={
                          inv.status === 'paid'
                            ? 'success'
                            : inv.status === 'posted'
                            ? 'neutral'
                            : 'warning'
                        }
                        size="sm"
                      >
                        {inv.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Proposals */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Sales Proposals
              </h3>
              <Link
                href="/sales-proposals"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentProposals.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No sales proposals recorded.
                </div>
              ) : (
                recentProposals.map((prop) => (
                  <div key={prop.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {prop.proposal_number}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {prop.customer_name}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {prop.issue_date}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-sky-400">
                        {formatCurrency(prop.grand_total)}
                      </div>
                      <Badge
                        variant={
                          prop.status === 'accepted'
                            ? 'success'
                            : prop.status === 'rejected'
                            ? 'danger'
                            : 'warning'
                        }
                        size="sm"
                      >
                        {prop.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
