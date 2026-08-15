import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import {
  Store,
  CreditCard,
  Banknote,
  RotateCcw,
  ShoppingBag,
  TrendingUp,
  Activity,
  Layers,
  ArrowRight,
  Package,
  Calendar,
  Sparkles,
  Users,
} from 'lucide-react';

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function PosDashboard() {
  const {
    stats = {},
    last10DaysSales = [],
    topProducts = [],
    paymentBreakdown = {},
    recentSales = [],
  } = usePage<any>().props;

  const todayRevenue = stats.today_revenue || 0;
  const todaySalesCount = stats.today_sales_count || 0;
  const totalRevenue = stats.total_revenue || 0;
  const avgOrderValue = stats.avg_order_value || 0;
  const activeCounters = stats.active_counters || 0;
  const returnsAmount = stats.returns_amount || 0;

  const maxDaily = Math.max(...last10DaysSales.map((d: any) => d.sales), 100);

  return (
    <AppShell title="Point of Sale Dashboard">
      <div className="space-y-6">
        <SectionHeader
          title="Point of Sale (POS) Terminal & Overview"
          description="Real-time checkout metrics, counter sessions, tender breakdowns, and velocity analytics."
          badge={<Badge variant="purple" size="sm">Retail & Counter POS</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/pos/orders">
                <Button variant="outline" size="sm" icon={<ShoppingBag className="w-3.5 h-3.5" />}>
                  POS Receipts
                </Button>
              </Link>
              <Link href="/pos/create">
                <Button variant="primary" size="sm" icon={<Store className="w-3.5 h-3.5" />}>
                  Launch Terminal
                </Button>
              </Link>
            </div>
          }
        />

        {/* Primary POS Metrics */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Today's POS Sales"
            value={formatCurrency(todayRevenue)}
            subtitle={`${todaySalesCount} checkout orders`}
            trend={{ value: '+14.8%', positive: true }}
            icon={<Store className="w-4 h-4 text-emerald-400" />}
          />

          <MetricCard
            title="Average Basket Value"
            value={formatCurrency(avgOrderValue)}
            subtitle="Per transaction average"
            icon={<ShoppingBag className="w-4 h-4 text-purple-400" />}
          />

          <MetricCard
            title="Active Billing Counters"
            value={String(activeCounters)}
            subtitle="Registers currently open"
            icon={<Layers className="w-4 h-4 text-sky-400" />}
          />

          <MetricCard
            title="Total Refunded Returns"
            value={formatCurrency(returnsAmount)}
            subtitle="Processed returns & voids"
            icon={<RotateCcw className="w-4 h-4 text-rose-400" />}
          />
        </div>

        {/* 10-Day Sales Trajectory & Tender Breakdown */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* 10-Day Visual Bar Chart */}
          <Card level={0} className="lg:col-span-2 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Activity className="w-4 h-4 text-purple-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  POS Revenue Velocity (Last 10 Days)
                </h3>
              </div>
              <Badge variant="neutral" size="sm">Daily Volume</Badge>
            </div>

            <div className="h-44 flex items-end justify-between gap-2 pt-4 px-2">
              {last10DaysSales.map((day: any) => {
                const pct = Math.min(100, Math.max(8, (day.sales / maxDaily) * 100));
                return (
                  <div key={day.date} className="flex-1 flex flex-col items-center gap-2 group">
                    <div className="w-full flex items-end justify-center h-32">
                      <div
                        className="w-full max-w-[28px] bg-gradient-to-t from-purple-600 to-purple-400 rounded-t transition-all group-hover:brightness-125 shadow-sm"
                        style={{ height: `${pct}%` }}
                        title={`${day.date}: ${formatCurrency(day.sales)}`}
                      />
                    </div>
                    <span className="text-[10px] font-semibold text-gray-400">{day.date}</span>
                  </div>
                );
              })}
            </div>
          </Card>

          {/* Tender / Payment Method Breakdown */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <CreditCard className="w-4 h-4 text-sky-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  Tender Distribution
                </h3>
              </div>
              <Badge variant="purple" size="sm">Payment Split</Badge>
            </div>

            <div className="space-y-3">
              {Object.keys(paymentBreakdown).length === 0 ? (
                <div className="text-xs text-gray-400 text-center py-6">
                  No tender data recorded yet for this workspace.
                </div>
              ) : (
                Object.entries(paymentBreakdown).map(([method, amount]: [string, any]) => (
                  <div key={method} className="space-y-1">
                    <div className="flex items-center justify-between text-xs">
                      <span className="capitalize text-gray-300 font-medium">{method.replace('_', ' ')}</span>
                      <span className="font-bold text-white">{formatCurrency(amount)}</span>
                    </div>
                    <div className="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                      <div
                        className="bg-sky-500 h-full rounded-full"
                        style={{ width: `${Math.min(100, (amount / Math.max(totalRevenue, 1)) * 100)}%` }}
                      />
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>
        </div>

        {/* Top 5 Products & Recent Completed Checkouts */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Top Selling Products */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Package className="w-4 h-4 text-emerald-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  Top Selling Products
                </h3>
              </div>
              <Badge variant="neutral" size="sm">By Units Sold</Badge>
            </div>

            <div className="divide-y divide-white/5">
              {topProducts.length === 0 ? (
                <div className="text-xs text-gray-400 text-center py-6">No sales recorded yet.</div>
              ) : (
                topProducts.map((p: any) => (
                  <div key={p.sku || p.name} className="py-2.5 flex items-center justify-between text-xs">
                    <div>
                      <p className="font-bold text-white">{p.name}</p>
                      <p className="text-[11px] text-gray-400 font-mono">SKU: {p.sku || 'N/A'}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-bold text-emerald-400">{formatCurrency(p.total_revenue)}</p>
                      <p className="text-[11px] text-gray-400">{p.total_quantity} units</p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent POS Checkouts */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Store className="w-4 h-4 text-purple-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  Recent POS Orders
                </h3>
              </div>
              <Link href="/pos/orders" className="text-xs text-purple-400 hover:text-purple-300 font-medium">
                View All →
              </Link>
            </div>

            <div className="divide-y divide-white/5">
              {recentSales.length === 0 ? (
                <div className="text-xs text-gray-400 text-center py-6">No recent sales.</div>
              ) : (
                recentSales.map((sale: any) => (
                  <div key={sale.id} className="py-2.5 flex items-center justify-between text-xs">
                    <div>
                      <span className="font-mono font-bold text-purple-300">
                        {sale.sale_number || `POS-${sale.id}`}
                      </span>
                      <span className="text-gray-400 ml-2">
                        {sale.customer_name || 'Walk-in Customer'}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge variant="neutral" size="sm">
                        {sale.payment_method || 'Cash'}
                      </Badge>
                      <span className="font-bold text-white">{formatCurrency(sale.total)}</span>
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
