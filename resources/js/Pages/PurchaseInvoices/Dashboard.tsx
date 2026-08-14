import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  Truck,
  DollarSign,
  TrendingUp,
  RotateCcw,
  Warehouse,
  CheckCircle2,
  Clock,
  Plus,
  ArrowRight,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { SimpleBarChart } from '@/Components/UI/Charts';

interface ProcurementStats {
  total_purchase_invoices: number;
  draft_purchase_invoices: number;
  posted_purchase_invoices: number;
  paid_purchase_invoices: number;
  total_purchasing_amount: number;
  outstanding_payables: number;
  total_purchase_returns: number;
  purchase_returns_amount: number;
  warehouses_count: number;
}

interface MonthlyPurchasesItem {
  month: string;
  purchases: number;
}

interface PurchaseInvoiceItem {
  id: number;
  invoice_number: string;
  vendor_name: string;
  grand_total: number;
  status: string;
  issue_date: string;
}

interface PurchaseReturnItem {
  id: number;
  return_number: string;
  vendor_name: string;
  total_amount: number;
  status: string;
  created_at: string;
}

interface ProcurementDashboardProps {
  stats: ProcurementStats;
  monthlyPurchases: MonthlyPurchasesItem[];
  recentInvoices: PurchaseInvoiceItem[];
  recentReturns: PurchaseReturnItem[];
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function PurchaseInvoicesDashboard({
  stats,
  monthlyPurchases = [],
  recentInvoices = [],
  recentReturns = [],
}: ProcurementDashboardProps) {
  const chartData = monthlyPurchases.map((item) => ({
    label: item.month,
    value: item.purchases,
    formattedValue: formatCurrency(item.purchases),
  }));

  return (
    <AppShell title="Procurement Dashboard">
      <Head title="Procurement & Purchasing Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="Procurement & Purchasing Dashboard"
            description="Vendor procurement accounts, payables lifecycle, supplier returns, and purchase order throughput."
          />
          <div className="flex items-center gap-3">
            <Link
              href="/purchase-returns"
              className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-2)] px-4 py-2.5 text-xs font-semibold text-[var(--text-primary)] border border-[var(--border-subtle)] hover:bg-[var(--surface-3)] transition"
            >
              <RotateCcw className="h-4 w-4 text-[var(--text-secondary)]" />
              Debit Notes
            </Link>
            <Link
              href="/purchase-invoices"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <Plus className="h-4 w-4" />
              Purchase Bills
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Purchases"
            value={formatCurrency(stats.total_purchasing_amount)}
            icon={<DollarSign className="h-5 w-5 text-amber-400" />}
            subtitle={`${stats.total_purchase_invoices} total vendor bills`}
          />
          <MetricCard
            title="Outstanding Payables"
            value={formatCurrency(stats.outstanding_payables)}
            icon={<Clock className="h-5 w-5 text-rose-400" />}
            trend={{
              value: stats.outstanding_payables > 0 ? 'Pending payment' : 'Fully settled',
              neutral: true,
            }}
          />
          <MetricCard
            title="Paid Bills"
            value={stats.paid_purchase_invoices}
            icon={<CheckCircle2 className="h-5 w-5 text-emerald-400" />}
            subtitle={`${stats.posted_purchase_invoices} posted, ${stats.draft_purchase_invoices} draft`}
          />
          <MetricCard
            title="Purchase Returns"
            value={stats.total_purchase_returns}
            icon={<RotateCcw className="h-5 w-5 text-sky-400" />}
            subtitle={formatCurrency(stats.purchase_returns_amount)}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Receiving Warehouses"
            value={stats.warehouses_count}
            icon={<Warehouse className="h-5 w-5 text-indigo-400" />}
            subtitle="Configured storage facilities"
          />
          <MetricCard
            title="Posted Vendor Bills"
            value={stats.posted_purchase_invoices}
            icon={<Truck className="h-5 w-5 text-sky-400" />}
            subtitle="Recognized liability documents"
          />
          <MetricCard
            title="Draft Bills"
            value={stats.draft_purchase_invoices}
            icon={<Clock className="h-5 w-5 text-[var(--text-tertiary)]" />}
            subtitle="Unposted draft entries"
          />
          <MetricCard
            title="Debit Notes Value"
            value={formatCurrency(stats.purchase_returns_amount)}
            icon={<DollarSign className="h-5 w-5 text-emerald-400" />}
            subtitle="Vendor credit returned"
          />
        </div>

        {/* Monthly Purchases Trend */}
        <Card level={0} className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Monthly Purchasing Volume
              </h3>
              <p className="text-xs text-[var(--text-tertiary)]">
                Vendor bills incurred across the last 6 procurement cycles
              </p>
            </div>
            <Badge variant="warning">6 Months</Badge>
          </div>
          <SimpleBarChart
            data={chartData}
            primaryLabel="Purchases"
            primaryColor="bg-amber-500"
            emptyMessage="No purchase invoices logged in the last 6 months."
          />
        </Card>

        {/* Recent Purchase Invoices & Returns Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Recent Invoices */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Purchase Bills
              </h3>
              <Link
                href="/purchase-invoices"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentInvoices.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No purchase invoices recorded.
                </div>
              ) : (
                recentInvoices.map((inv) => (
                  <div key={inv.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {inv.invoice_number}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {inv.vendor_name}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {inv.issue_date}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-amber-400">
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

          {/* Recent Returns */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Purchase Returns
              </h3>
              <Link
                href="/purchase-returns"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentReturns.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No purchase returns recorded.
                </div>
              ) : (
                recentReturns.map((ret) => (
                  <div key={ret.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {ret.return_number}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {ret.vendor_name}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {ret.created_at}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        {formatCurrency(ret.total_amount)}
                      </div>
                      <Badge variant="neutral" size="sm">
                        {ret.status}
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
