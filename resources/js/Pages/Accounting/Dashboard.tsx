import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  Users,
  Building2,
  DollarSign,
  TrendingUp,
  TrendingDown,
  CreditCard,
  ArrowUpRight,
  ArrowDownRight,
  Plus,
  BookOpen,
  FileText,
  Layers,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { SimpleBarChart } from '@/Components/UI/Charts';

interface DashboardStats {
  total_clients: number;
  total_vendors: number;
  total_revenue: number;
  total_expense: number;
  total_customer_payment: number;
  total_vendor_payment: number;
  net_profit: number;
  cash_bank_balance: number;
  receivables: number;
  payables: number;
}

interface MonthlyPayment {
  month: string;
  customer_payments?: number;
  vendor_payments?: number;
}

interface TransactionItem {
  id: number;
  title: string;
  description: string;
  amount: number;
  date: string;
  status: string;
}

interface JournalItem {
  id: number;
  reference: string;
  description: string;
  entry_date: string;
  status: string;
}

interface FinancialHealth {
  assets: number;
  liabilities: number;
  equity: number;
  net_income: number;
}

interface AccountingDashboardProps {
  stats: DashboardStats;
  monthlyCustomerPayments: MonthlyPayment[];
  monthlyVendorPayments: MonthlyPayment[];
  recentRevenues: TransactionItem[];
  recentExpenses: TransactionItem[];
  recentJournals: JournalItem[];
  financialHealth: FinancialHealth;
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function AccountingDashboard({
  stats,
  monthlyCustomerPayments = [],
  monthlyVendorPayments = [],
  recentRevenues = [],
  recentExpenses = [],
  recentJournals = [],
  financialHealth,
}: AccountingDashboardProps) {
  const customerChartData = monthlyCustomerPayments.map((item) => ({
    label: item.month,
    value: item.customer_payments || 0,
    formattedValue: formatCurrency(item.customer_payments || 0),
  }));

  const vendorChartData = monthlyVendorPayments.map((item) => ({
    label: item.month,
    value: item.vendor_payments || 0,
    formattedValue: formatCurrency(item.vendor_payments || 0),
  }));

  return (
    <AppShell title="Account Dashboard">
      <Head title="Account & Finance Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="Account & Finance Dashboard"
            description="Real-time financial performance, cash flow, receivable pipelines, and ledger status."
          />
          <div className="flex items-center gap-3">
            <Link
              href="/accounting/journals"
              className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-2)] px-4 py-2.5 text-xs font-semibold text-[var(--text-primary)] border border-[var(--border-subtle)] hover:bg-[var(--surface-3)] transition"
            >
              <BookOpen className="h-4 w-4 text-[var(--text-secondary)]" />
              Journal Entries
            </Link>
            <Link
              href="/accounting/accounts"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <Plus className="h-4 w-4" />
              Chart of Accounts
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Clients"
            value={stats.total_clients}
            icon={<Users className="h-5 w-5 text-indigo-400" />}
            subtitle="Active paying customers"
          />
          <MetricCard
            title="Total Vendors"
            value={stats.total_vendors}
            icon={<Building2 className="h-5 w-5 text-sky-400" />}
            subtitle="Suppliers & service providers"
          />
          <MetricCard
            title="Customer Payments"
            value={formatCurrency(stats.total_customer_payment)}
            icon={<ArrowDownRight className="h-5 w-5 text-emerald-400" />}
            trend={{
              value: stats.receivables > 0 ? `${formatCurrency(stats.receivables)} pending` : 'All collected',
              positive: stats.receivables === 0,
            }}
          />
          <MetricCard
            title="Vendor Payments"
            value={formatCurrency(stats.total_vendor_payment)}
            icon={<ArrowUpRight className="h-5 w-5 text-amber-400" />}
            trend={{
              value: stats.payables > 0 ? `${formatCurrency(stats.payables)} outstanding` : 'Fully paid',
              neutral: true,
            }}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Revenue"
            value={formatCurrency(stats.total_revenue)}
            icon={<TrendingUp className="h-5 w-5 text-emerald-400" />}
            subtitle="Recognized earned income"
          />
          <MetricCard
            title="Total Expenses"
            value={formatCurrency(stats.total_expense)}
            icon={<TrendingDown className="h-5 w-5 text-rose-400" />}
            subtitle="Operational & COGS expense"
          />
          <MetricCard
            title="Net Profit"
            value={formatCurrency(stats.net_profit)}
            icon={<DollarSign className="h-5 w-5 text-emerald-400" />}
            trend={{
              value: stats.net_profit >= 0 ? 'Profitable' : 'Deficit',
              positive: stats.net_profit >= 0,
            }}
          />
          <MetricCard
            title="Cash & Bank Balance"
            value={formatCurrency(stats.cash_bank_balance)}
            icon={<CreditCard className="h-5 w-5 text-violet-400" />}
            subtitle="Liquid available funds"
          />
        </div>

        {/* Payment Trends Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Monthly Customer Payments Chart */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                  Customer Payments Trend
                </h3>
                <p className="text-xs text-[var(--text-tertiary)]">
                  Monthly cash collection velocity over the last 6 months
                </p>
              </div>
              <Badge variant="success">Collections</Badge>
            </div>
            <SimpleBarChart
              data={customerChartData}
              primaryLabel="Collected"
              primaryColor="bg-emerald-500"
              emptyMessage="No customer payments recorded in the last 6 months."
            />
          </Card>

          {/* Monthly Vendor Payments Chart */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                  Vendor Payments Trend
                </h3>
                <p className="text-xs text-[var(--text-tertiary)]">
                  Monthly supplier disbursements over the last 6 months
                </p>
              </div>
              <Badge variant="warning">Disbursements</Badge>
            </div>
            <SimpleBarChart
              data={vendorChartData}
              primaryLabel="Disbursed"
              primaryColor="bg-amber-500"
              emptyMessage="No vendor payments recorded in the last 6 months."
            />
          </Card>
        </div>

        {/* Accounting Workflows & Directories */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <Link
            href="/accounting/customers"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Customers</span>
              <Users className="w-3.5 h-3.5 text-indigo-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Directory & receivables</p>
          </Link>

          <Link
            href="/accounting/vendors"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Vendors</span>
              <Building2 className="w-3.5 h-3.5 text-sky-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Suppliers & payables</p>
          </Link>

          <Link
            href="/accounting/customer-payments"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Customer Payments</span>
              <ArrowDownRight className="w-3.5 h-3.5 text-emerald-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Collections & receipts</p>
          </Link>

          <Link
            href="/accounting/vendor-payments"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Vendor Payments</span>
              <ArrowUpRight className="w-3.5 h-3.5 text-amber-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Disbursements & bills</p>
          </Link>

          <Link
            href="/accounting/revenues"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Direct Revenues</span>
              <TrendingUp className="w-3.5 h-3.5 text-emerald-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Non-invoice income</p>
          </Link>

          <Link
            href="/accounting/expenses"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Direct Expenses</span>
              <TrendingDown className="w-3.5 h-3.5 text-rose-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Operating costs</p>
          </Link>

          <Link
            href="/accounting/credit-notes"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Credit Notes</span>
              <FileText className="w-3.5 h-3.5 text-amber-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Customer adjustments</p>
          </Link>

          <Link
            href="/accounting/debit-notes"
            className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-purple-500/40 hover:bg-white/[0.02] spring-transition space-y-1 block"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--text-primary)]">Debit Notes</span>
              <FileText className="w-3.5 h-3.5 text-rose-400" />
            </div>
            <p className="text-[11px] text-[var(--text-tertiary)]">Vendor debit claims</p>
          </Link>
        </div>

        {/* Financial Position Snapshot */}
        <Card level={0} className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Layers className="h-4 w-4 text-violet-400" />
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Balance Sheet & Profitability Snapshot
              </h3>
            </div>
            <Link
              href="/accounting/reports"
              className="text-xs font-semibold text-violet-400 hover:text-violet-300 transition"
            >
              View Full Reports →
            </Link>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
            <div className="p-3 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] space-y-1">
              <span className="text-xs text-[var(--text-tertiary)]">Total Assets</span>
              <div className="text-base font-bold text-[var(--text-primary)]">
                {formatCurrency(financialHealth?.assets || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] space-y-1">
              <span className="text-xs text-[var(--text-tertiary)]">Total Liabilities</span>
              <div className="text-base font-bold text-rose-400">
                {formatCurrency(financialHealth?.liabilities || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] space-y-1">
              <span className="text-xs text-[var(--text-tertiary)]">Total Equity</span>
              <div className="text-base font-bold text-sky-400">
                {formatCurrency(financialHealth?.equity || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] space-y-1">
              <span className="text-xs text-[var(--text-tertiary)]">Net Operating Income</span>
              <div className="text-base font-bold text-emerald-400">
                {formatCurrency(financialHealth?.net_income || 0)}
              </div>
            </div>
          </div>
        </Card>

        {/* Recent Transactions & Journals Grid */}
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Recent Revenues */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Revenue Invoices
              </h3>
              <Link
                href="/sales-invoices"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentRevenues.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No revenue invoices recorded.
                </div>
              ) : (
                recentRevenues.map((item) => (
                  <div key={item.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {item.title}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {item.description}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {item.date}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        +{formatCurrency(item.amount)}
                      </div>
                      <Badge variant={item.status === 'paid' ? 'success' : 'neutral'} size="sm">
                        {item.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Expenses */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Expense Bills
              </h3>
              <Link
                href="/purchase-invoices"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentExpenses.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No expense bills recorded.
                </div>
              ) : (
                recentExpenses.map((item) => (
                  <div key={item.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {item.title}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {item.description}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {item.date}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-rose-400">
                        -{formatCurrency(item.amount)}
                      </div>
                      <Badge variant={item.status === 'paid' ? 'success' : 'neutral'} size="sm">
                        {item.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Journals */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Latest General Journals
              </h3>
              <Link
                href="/accounting/journals"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentJournals.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No journal entries recorded.
                </div>
              ) : (
                recentJournals.map((item) => (
                  <div key={item.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="flex items-center gap-1.5">
                        <FileText className="h-3.5 w-3.5 text-violet-400" />
                        <span className="text-xs font-semibold text-[var(--text-primary)] truncate">
                          {item.reference}
                        </span>
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {item.description}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {item.entry_date}
                      </div>
                    </div>
                    <Badge variant={item.status === 'posted' ? 'success' : 'warning'} size="sm">
                      {item.status}
                    </Badge>
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
