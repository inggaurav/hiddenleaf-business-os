import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Building2, CreditCard, DollarSign, Layers, Package, Settings, ShoppingCart, Sliders, Users } from 'lucide-react';

const shortcuts = [
  { name: 'Companies', href: '/super-admin/companies', icon: Building2, description: 'Customer tenants, owners, plans and access state.' },
  { name: 'Plans', href: '/plans', icon: CreditCard, description: 'Pricing, limits and module entitlements.' },
  { name: 'Orders', href: '/orders', icon: ShoppingCart, description: 'Subscription and payment order history.' },
  { name: 'Subscriptions', href: '/subscriptions', icon: CreditCard, description: 'Active customer subscription records.' },
  { name: 'Modules & Add-ons', href: '/modules', icon: Package, description: 'Installed capabilities and workspace activation.' },
  { name: 'Global Settings', href: '/super-admin/settings', icon: Settings, description: 'Platform configuration and integrations.' },
];

export default function SuperAdminDashboard() {
  const { metrics = {}, monthlyOrders = [], ticketStatus = {}, weeklyPendingTickets = [], recentTickets = [] } = usePage<any>().props;

  return (
    <AppShell title="Super Admin Control Center" breadcrumbs={[{ label: 'Platform Administration' }, { label: 'Dashboard' }]}>
      <div className="space-y-6">
        <SectionHeader
          title="Super Admin Control Center"
          description="Master tenant telemetry, companies, licensing, billing, modules and global configuration."
          badge={<Badge variant="purple" size="sm">Global Root</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/super-admin/companies"><Button variant="secondary" size="sm" icon={<Building2 className="w-4 h-4" />}>Companies</Button></Link>
              <Link href="/plans"><Button variant="primary" size="sm" icon={<CreditCard className="w-4 h-4" />}>Manage Plans</Button></Link>
            </div>
          }
        />

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard title="Companies" value={metrics.organizations ?? 0} icon={<Building2 className="w-5 h-5 text-violet-400" />} subtitle="Customer organizations" />
          <MetricCard title="Global Tenant Users" value={metrics.users ?? 0} icon={<Users className="w-5 h-5 text-violet-400" />} subtitle="Registered accounts" />
          <MetricCard title="Total Workspaces" value={metrics.workspaces ?? 0} icon={<Layers className="w-5 h-5 text-indigo-400" />} subtitle="Isolated workspaces" />
          <MetricCard title="Active Subscriptions" value={metrics.active_subscriptions ?? 0} icon={<CreditCard className="w-5 h-5 text-purple-400" />} subtitle="Current subscriptions" />
          <MetricCard title="Processed Orders" value={metrics.orders ?? 0} icon={<ShoppingCart className="w-5 h-5 text-purple-400" />} subtitle="Billing orders" />
          <MetricCard title="Revenue" value={`$${Number(metrics.revenue || 0).toFixed(2)}`} icon={<DollarSign className="w-5 h-5 text-emerald-400" />} subtitle="Paid/completed orders" />
          <MetricCard title="Pending Bank Transfers" value={metrics.pending_bank_transfers ?? 0} icon={<DollarSign className="w-5 h-5 text-amber-400" />} subtitle="Awaiting review" />
          <MetricCard title="Open Helpdesk Tickets" value={metrics.open_helpdesk_tickets ?? 0} icon={<Sliders className="w-5 h-5 text-rose-400" />} subtitle="Support workload" />
        </div>

        <div className="grid xl:grid-cols-2 gap-5">
          <Card level={0} className="p-5"><h3 className="text-sm font-bold mb-4">Recent Orders (Monthly)</h3><div className="space-y-3">{monthlyOrders.map((row: any) => { const max = Math.max(1, ...monthlyOrders.map((item: any) => Number(item.orders))); return <div key={row.month} className="grid grid-cols-[36px_1fr_auto] gap-3 items-center text-xs"><span>{row.month}</span><div className="h-2 rounded-full bg-white/5 overflow-hidden"><div className="h-full bg-purple-500" style={{ width: `${(Number(row.orders) / max) * 100}%` }} /></div><span>{row.orders} orders • ${Number(row.revenue).toFixed(2)}</span></div>; })}</div></Card>
          <Card level={0} className="p-5"><h3 className="text-sm font-bold mb-4">Helpdesk Ticket Status</h3><div className="grid sm:grid-cols-2 gap-3">{Object.entries(ticketStatus).map(([status, total]: any) => <div key={status} className="flex items-center justify-between p-3 rounded-xl border border-[var(--border-subtle)]"><span className="capitalize text-xs">{status}</span><Badge variant="neutral" size="sm">{total}</Badge></div>)}</div><h4 className="text-xs font-bold mt-5 mb-3">Weekly Pending Tickets</h4><div className="flex items-end gap-2 h-24">{weeklyPendingTickets.map((row: any) => { const max = Math.max(1, ...weeklyPendingTickets.map((item: any) => Number(item.tickets))); return <div key={row.day} className="flex-1 flex flex-col items-center justify-end gap-1 h-full"><span className="text-[10px]">{row.tickets}</span><div className="w-full bg-amber-500/70 rounded-t" style={{ height: `${Math.max(4, (Number(row.tickets) / max) * 70)}px` }} /><span className="text-[10px] text-[var(--text-tertiary)]">{row.day}</span></div>; })}</div></Card>
        </div>

        <Card level={0} className="overflow-x-auto"><div className="p-4 border-b border-[var(--border-subtle)]"><h3 className="text-sm font-bold">Recent Helpdesk Tickets</h3></div><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-3">Ticket</th><th>Subject</th><th>Requester</th><th>Category</th><th>Status</th><th>Created</th></tr></thead><tbody>{recentTickets.map((ticket: any) => <tr key={ticket.id} className="border-b border-[var(--border-subtle)]"><td className="p-3">#{ticket.id}</td><td>{ticket.subject || ticket.title}</td><td>{ticket.creator?.name || ticket.name || '—'}</td><td>{ticket.category?.name || '—'}</td><td><Badge variant="neutral" size="sm">{ticket.status}</Badge></td><td>{ticket.created_at}</td></tr>)}</tbody></table></Card>

        <div className="space-y-3">
          <h2 className="text-sm font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Platform Management</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {shortcuts.map((item) => {
              const Icon = item.icon;
              return (
                <Link key={item.href} href={item.href}>
                  <Card level={0} className="h-full hover:border-purple-500/40 transition-colors">
                    <div className="flex items-start gap-3"><div className="w-9 h-9 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center"><Icon className="w-4.5 h-4.5 text-purple-400" /></div><div><h3 className="text-sm font-bold">{item.name}</h3><p className="text-xs text-[var(--text-tertiary)] mt-1 leading-relaxed">{item.description}</p></div></div>
                  </Card>
                </Link>
              );
            })}
          </div>
        </div>
      </div>
    </AppShell>
  );
}
