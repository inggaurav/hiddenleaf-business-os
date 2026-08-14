import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  Store,
  Receipt,
  DollarSign,
  TrendingUp,
  CreditCard,
  RotateCcw,
  AlertTriangle,
  Package,
  Plus,
  ArrowRight,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { SimpleBarChart, ProgressDistribution } from '@/Components/UI/Charts';

interface PosStats {
  today_orders: number;
  today_revenue: number;
  total_sales: number;
  total_revenue: number;
  avg_order_value: number;
  open_registers: number;
  total_registers: number;
  total_products: number;
  total_returns: number;
  returns_amount: number;
  low_stock_count: number;
}

interface SalesTrendItem {
  date: string;
  sales: number;
}

interface TopProductItem {
  name: string;
  sku: string;
  total_quantity: number;
  total_revenue: number;
}

interface PosOrderItem {
  id: number;
  receipt_number: string;
  customer_name: string;
  payment_method: string;
  grand_total: number;
  status: string;
  created_at: string;
}

interface PosReturnItem {
  id: number;
  return_number: string;
  refund_total: number;
  reason: string;
  created_at: string;
}

interface PosDashboardProps {
  stats: PosStats;
  paymentBreakdown: Record<string, number>;
  last10DaysSales: SalesTrendItem[];
  topProducts: TopProductItem[];
  recentOrders: PosOrderItem[];
  recentReturns: PosReturnItem[];
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function PosDashboard({
  stats,
  paymentBreakdown = {},
  last10DaysSales = [],
  topProducts = [],
  recentOrders = [],
  recentReturns = [],
}: PosDashboardProps) {
  const chartData = last10DaysSales.map((item) => ({
    label: item.date,
    value: item.sales,
    formattedValue: formatCurrency(item.sales),
  }));

  const paymentItems = Object.entries(paymentBreakdown).map(([method, amount]) => ({
    name: method.replace(/_/g, ' ').toUpperCase(),
    value: amount,
    formattedValue: formatCurrency(amount),
  }));

  return (
    <AppShell title="POS Dashboard">
      <Head title="Point of Sale Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="Point of Sale Dashboard"
            description="Real-time register activity, counter sales throughput, best-selling inventory, and cash drawers."
          />
          <div className="flex items-center gap-3">
            <Link
              href="/pos/terminal"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <Store className="h-4 w-4" />
              Launch POS Terminal
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Today's Orders"
            value={stats.today_orders}
            icon={<Receipt className="h-5 w-5 text-indigo-400" />}
            subtitle={`${formatCurrency(stats.today_revenue)} today's revenue`}
          />
          <MetricCard
            title="Total POS Revenue"
            value={formatCurrency(stats.total_revenue)}
            icon={<DollarSign className="h-5 w-5 text-emerald-400" />}
            subtitle={`${stats.total_sales} total completed orders`}
          />
          <MetricCard
            title="Average Order Value"
            value={formatCurrency(stats.avg_order_value)}
            icon={<TrendingUp className="h-5 w-5 text-sky-400" />}
            subtitle="Per checkout average"
          />
          <MetricCard
            title="Open Registers"
            value={stats.open_registers}
            icon={<Store className="h-5 w-5 text-violet-400" />}
            subtitle={`${stats.total_registers} registers deployed`}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Low Stock Items"
            value={stats.low_stock_count}
            icon={<AlertTriangle className="h-5 w-5 text-amber-400" />}
            trend={{
              value: stats.low_stock_count > 0 ? 'Restock required' : 'Stocked',
              positive: stats.low_stock_count === 0,
            }}
          />
          <MetricCard
            title="Total Returns"
            value={stats.total_returns}
            icon={<RotateCcw className="h-5 w-5 text-rose-400" />}
            subtitle={formatCurrency(stats.returns_amount)}
          />
          <MetricCard
            title="POS Items in Catalog"
            value={stats.total_products}
            icon={<Package className="h-5 w-5 text-sky-400" />}
            subtitle="Products available at checkout"
          />
          <MetricCard
            title="Register Health"
            value={stats.open_registers > 0 ? 'Active' : 'Closed'}
            icon={<Store className="h-5 w-5 text-emerald-400" />}
            subtitle={`${stats.open_registers} active session(s)`}
          />
        </div>

        {/* Charts Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Last 10 Days Sales Trend */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                  Last 10 Days Sales Trend
                </h3>
                <p className="text-xs text-[var(--text-tertiary)]">
                  Daily POS checkout revenue velocity
                </p>
              </div>
              <Badge variant="success">Daily Revenue</Badge>
            </div>
            <SimpleBarChart
              data={chartData}
              primaryLabel="Sales"
              primaryColor="bg-emerald-500"
              emptyMessage="No POS orders in the last 10 days."
            />
          </Card>

          {/* Payment Method Breakdown */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                  Payment Method Breakdown
                </h3>
                <p className="text-xs text-[var(--text-tertiary)]">
                  Tender distribution across cash, card, and digital methods
                </p>
              </div>
              <CreditCard className="h-4 w-4 text-violet-400" />
            </div>
            {paymentItems.length === 0 ? (
              <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                No tender transactions recorded yet.
              </div>
            ) : (
              <ProgressDistribution items={paymentItems} />
            )}
          </Card>
        </div>

        {/* Top Selling Products & Tables */}
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Top Selling Products */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Top Selling Products
              </h3>
              <Badge variant="neutral">Best Sellers</Badge>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {topProducts.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No sales recorded yet.
                </div>
              ) : (
                topProducts.map((prod, idx) => (
                  <div key={idx} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {prod.name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        SKU: {prod.sku} • {prod.total_quantity} units sold
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        {formatCurrency(prod.total_revenue)}
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent POS Orders */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent POS Receipts
              </h3>
              <Link
                href="/pos/terminal"
                className="text-xs text-violet-400 hover:underline"
              >
                Open Terminal
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentOrders.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No POS orders recorded.
                </div>
              ) : (
                recentOrders.map((order) => (
                  <div key={order.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {order.receipt_number}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {order.customer_name} • {order.payment_method}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {order.created_at}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        {formatCurrency(order.grand_total)}
                      </div>
                      <Badge variant="success" size="sm">
                        {order.status}
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
                Recent POS Returns
              </h3>
              <RotateCcw className="h-4 w-4 text-rose-400" />
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentReturns.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No returns recorded.
                </div>
              ) : (
                recentReturns.map((ret) => (
                  <div key={ret.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {ret.return_number}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {ret.reason}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {ret.created_at}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-rose-400">
                        -{formatCurrency(ret.refund_total)}
                      </div>
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
