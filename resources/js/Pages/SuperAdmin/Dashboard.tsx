import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import {
  Building,
  CreditCard,
  DollarSign,
  Headphones,
  Layers,
  PackagePlus,
  ShoppingCart,
  Sliders,
  Users,
} from 'lucide-react';
import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

export default function SuperAdminDashboard() {
  const { metrics = {}, chartData = { labels: [], orders: [], payments: [] } } = usePage<any>().props;

  const monthlyData = (chartData.labels || []).map((month: string, index: number) => ({
    month,
    orders: chartData.orders?.[index] ?? 0,
    payments: chartData.payments?.[index] ?? 0,
  }));

  return (
    <AppShell title="Super Admin Dashboard">
      <div className="space-y-6">
        <SectionHeader
          title="Super Admin Dashboard"
          description="Platform-wide companies, plans, orders, payments, support, modules, and system administration."
          badge={<Badge variant="purple" size="sm">Super Admin</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/modules">
                <Button variant="secondary" size="sm" icon={<PackagePlus className="w-4 h-4" />}>
                  Add-ons
                </Button>
              </Link>
              <Link href="/super-admin/settings">
                <Button variant="primary" size="sm" icon={<Sliders className="w-4 h-4" />}>
                  Settings
                </Button>
              </Link>
            </div>
          }
        />

        {/* WorkDo reference KPI contract. */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Order Payments"
            value={Number(metrics.order_payments ?? 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}
            icon={<DollarSign className="w-5 h-5 text-emerald-400" />}
            subtitle="All subscription order value"
          />
          <MetricCard
            title="Total Orders"
            value={metrics.total_orders ?? 0}
            icon={<ShoppingCart className="w-5 h-5 text-violet-400" />}
            subtitle="Subscription orders"
          />
          <MetricCard
            title="Total Plans"
            value={metrics.total_plans ?? 0}
            icon={<CreditCard className="w-5 h-5 text-purple-400" />}
            subtitle="Configured SaaS plans"
          />
          <MetricCard
            title="Total Companies"
            value={metrics.total_companies ?? 0}
            icon={<Building className="w-5 h-5 text-indigo-400" />}
            subtitle="Registered organizations"
          />
        </div>

        <Card className="p-5">
          <div className="mb-4">
            <h2 className="text-sm font-semibold text-[var(--text-primary)]">Orders & Payments</h2>
            <p className="mt-1 text-xs text-[var(--text-tertiary)]">January–December platform order activity for the current year.</p>
          </div>
          <div className="h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={monthlyData}>
                <CartesianGrid strokeDasharray="3 3" opacity={0.15} />
                <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                <YAxis yAxisId="orders" tick={{ fontSize: 11 }} allowDecimals={false} />
                <YAxis yAxisId="payments" orientation="right" tick={{ fontSize: 11 }} />
                <Tooltip />
                <Legend />
                <Line yAxisId="orders" type="monotone" dataKey="orders" name="Orders" stroke="currentColor" strokeWidth={2} dot={false} />
                <Line yAxisId="payments" type="monotone" dataKey="payments" name="Payments" stroke="currentColor" strokeWidth={2} strokeDasharray="5 4" dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </Card>

        {/* HiddenLeaf superset metrics remain available below the reference dashboard. */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Global Users"
            value={metrics.users ?? 0}
            icon={<Users className="w-5 h-5 text-violet-400" />}
            subtitle="Registered accounts"
          />
          <MetricCard
            title="Workspaces"
            value={metrics.workspaces ?? 0}
            icon={<Layers className="w-5 h-5 text-indigo-400" />}
            subtitle="Isolated tenant workspaces"
          />
          <MetricCard
            title="Paid Revenue"
            value={Number(metrics.revenue ?? 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}
            icon={<DollarSign className="w-5 h-5 text-emerald-400" />}
            subtitle="Paid/completed order revenue"
          />
          <MetricCard
            title="Open Helpdesk"
            value={metrics.open_helpdesk_tickets ?? 0}
            icon={<Headphones className="w-5 h-5 text-amber-400" />}
            subtitle="Unresolved platform tickets"
          />
        </div>
      </div>
    </AppShell>
  );
}
