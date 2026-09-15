import React, { useEffect, useState } from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { MrFoxMark } from '@/Components/MrFox/MrFoxMark';
import { MrFoxPanel } from '@/Components/MrFox/MrFoxPanel';
import { formatINRShort, formatNumber, formatDate, greeting } from '@/lib/format';
import { Sparkles, ChevronRight, CheckCircle2, Wallet, TrendingUp, DollarSign, AlertCircle, Users, FolderKanban, Store, Boxes, Truck } from 'lucide-react';

export default function Dashboard() {
  const { auth, tenant, metrics } = usePage<any>().props;
  const user = auth?.user;
  const workspaceTitle = tenant?.workspace_title || 'Workspace';
  const enabledModules: string[] = (tenant?.modules || []).map((m: string) => m.toLowerCase());
  const [insights, setInsights] = useState<any[]>([]);
  const [loadingInsights, setLoadingInsights] = useState(true);
  const [isFoxOpen, setIsFoxOpen] = useState(false);

  useEffect(() => {
    fetch('/api/v1/mr-fox/insights')
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => { if (Array.isArray(data?.insights)) setInsights(data.insights); })
      .catch(() => setInsights([]))
      .finally(() => setLoadingInsights(false));
  }, []);

  const hasMod = (name: string) => enabledModules.length === 0 || enabledModules.includes(name);
  const salesTotal = Number(metrics?.sales ?? 0);
  const purchaseTotal = Number(metrics?.purchases ?? 0);
  const overdueCount = Number(metrics?.overdue_invoices ?? 0);
  const cashPos = Number(metrics?.cash_position ?? (salesTotal - purchaseTotal));

  const attentionItems = [
    overdueCount > 0 && { id: 'inv', label: `${overdueCount} invoices overdue 14+ days`, href: '/sales', dot: 'bg-rose-400' },
    Number(metrics?.low_stock ?? 0) > 0 && { id: 'stk', label: `${metrics.low_stock} items below reorder point`, href: '/inventory', dot: 'bg-amber-400' },
    Number(metrics?.open_tasks ?? 0) > 0 && { id: 'tsk', label: `${metrics.open_tasks} project tasks pending completion`, href: '/taskly', dot: 'bg-amber-400' },
    Number(metrics?.open_leads ?? 0) > 0 && { id: 'cld', label: `${metrics.open_leads} active leads requiring follow-up`, href: '/crm', dot: 'bg-blue-400' },
    Number(metrics?.open_tickets ?? 0) > 0 && { id: 'tkt', label: `${metrics.open_tickets} customer tickets waiting on response`, href: '/helpdesk', dot: 'bg-amber-400' },
  ].filter(Boolean) as { id: string; label: string; href: string; dot: string }[];

  const modules = [
    { name: 'Accounting', href: '/accounting', icon: Wallet, value: formatINRShort(cashPos), valueLabel: 'Cash position', active: hasMod('account') || hasMod('accounting') },
    { name: 'Sales', href: '/sales', icon: TrendingUp, value: formatINRShort(salesTotal), valueLabel: 'Sales recognized', active: hasMod('sales') || hasMod('pos') },
    { name: 'HRM', href: '/hrm', icon: Users, value: formatNumber(metrics?.members ?? 1), valueLabel: 'Team members', active: hasMod('hrm') },
    { name: 'CRM', href: '/crm', icon: Sparkles, value: formatNumber(metrics?.open_leads ?? 0), valueLabel: 'Active leads', active: hasMod('crm') },
    { name: 'Taskly', href: '/taskly', icon: FolderKanban, value: formatNumber(metrics?.active_projects ?? 0), valueLabel: 'Active projects', active: hasMod('taskly') },
    { name: 'Inventory', href: '/inventory', icon: Boxes, value: formatNumber(metrics?.products ?? 0), valueLabel: 'Tracked items', active: hasMod('productservice') },
    { name: 'Procurement', href: '/purchase-invoices-dashboard', icon: Truck, value: formatINRShort(purchaseTotal), valueLabel: 'Purchases MTD', active: hasMod('procurement') || hasMod('purchase') },
    { name: 'POS', href: '/pos/dashboard', icon: Store, value: formatINRShort(metrics?.today_pos_sales ?? 0), valueLabel: 'Sales today', active: hasMod('pos') },
  ].filter((m) => m.active);

  return (
    <AppShell title="Dashboard">
      <div className="space-y-6">
        {/* 1. Greeting bar */}
        <div className="flex items-end justify-between pb-5 border-b border-[var(--border-subtle)]">
          <div>
            <h1 className="text-xl font-bold text-[var(--text-primary)]">{greeting()}, {user?.name ? user.name.split(' ')[0] : 'there'}</h1>
            <p className="text-sm text-[var(--text-secondary)] mt-0.5">{workspaceTitle} · {formatDate(new Date())}</p>
          </div>
          <Button variant="intelligence" size="sm" icon={<Sparkles className="w-3.5 h-3.5" />} onClick={() => setIsFoxOpen(true)}>
            Ask Mr. Fox
          </Button>
        </div>

        {/* 2. Four metric cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard title="Cash position" value={hasMod('account') ? formatINRShort(cashPos) : '—'} subtitle={!hasMod('account') ? 'Enable Accounting' : undefined} icon={<Wallet className="w-4 h-4" />} />
          <MetricCard title="Revenue this month" value={hasMod('sales') ? formatINRShort(salesTotal) : '—'} subtitle={!hasMod('sales') ? 'Enable Sales' : undefined} icon={<TrendingUp className="w-4 h-4" />} />
          <MetricCard title="Payables due 30d" value={hasMod('procurement') ? formatINRShort(purchaseTotal) : '—'} subtitle={!hasMod('procurement') ? 'Enable Procurement' : undefined} icon={<DollarSign className="w-4 h-4" />} />
          <MetricCard title="Overdue invoices" value={hasMod('sales') ? formatNumber(overdueCount) : '—'} subtitle={!hasMod('sales') ? 'Enable Sales' : undefined} icon={<AlertCircle className="w-4 h-4" />} />
        </div>

        {/* 3. Needs attention + Mr. Fox */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2">
            <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">Needs Attention</h2>
            {attentionItems.length === 0 ? (
              <div className="flex items-center gap-2 py-4 text-sm text-[var(--text-secondary)]">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                <span>Nothing needs your attention.</span>
              </div>
            ) : (
              <div className="divide-y divide-[var(--border-subtle)]">
                {attentionItems.slice(0, 6).map((item) => (
                  <Link href={item.href} key={item.id} className="flex items-center justify-between py-3 group">
                    <div className="flex items-center gap-3 min-w-0">
                      <span className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${item.dot}`} />
                      <span className="text-sm text-[var(--text-primary)] truncate">{item.label}</span>
                    </div>
                    <ChevronRight className="w-4 h-4 text-[var(--text-tertiary)] group-hover:text-[var(--text-secondary)]" />
                  </Link>
                ))}
                {attentionItems.length > 6 && (
                  <div className="py-2 text-xs text-[var(--text-tertiary)]">{attentionItems.length - 6} more items →</div>
                )}
              </div>
            )}
          </div>

          <Card level={2} className="flex flex-col justify-between">
            <div>
              <div className="flex items-center gap-2 mb-3">
                <MrFoxMark size={16} />
                <span className="text-xs font-semibold uppercase tracking-wider text-[var(--brand-primary)]">Mr. Fox</span>
              </div>
              {loadingInsights ? (
                <div className="space-y-2 animate-pulse">
                  <div className="h-3 bg-[var(--surface-3)] rounded w-3/4" /><div className="h-3 bg-[var(--surface-3)] rounded w-full" />
                </div>
              ) : (
                <p className="text-sm text-[var(--text-secondary)] leading-relaxed">
                  {insights[0]?.summary || insights[0]?.text || 'All operational systems are operating normally. Cash flow and pending tasks are well within targets.'}
                </p>
              )}
            </div>
            <div className="mt-4">
              <Button variant="intelligence" size="sm" onClick={() => setIsFoxOpen(true)}>Open chat</Button>
            </div>
          </Card>
        </div>

        {/* 4. Compact module grid */}
        <div>
          <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">Your Modules</h2>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            {modules.map((m) => (
              <Link href={m.href} key={m.name}>
                <div className="p-4 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] spring-transition">
                  <div className="flex items-center gap-2.5 mb-2">
                    <m.icon className="w-4 h-4 text-[var(--text-tertiary)]" />
                    <span className="text-sm font-medium text-[var(--text-primary)]">{m.name}</span>
                  </div>
                  <div className="text-lg font-bold tabular-nums text-[var(--text-primary)]">{m.value}</div>
                  <div className="text-xs text-[var(--text-tertiary)]">{m.valueLabel}</div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </div>

      <MrFoxPanel isOpen={isFoxOpen} onClose={() => setIsFoxOpen(false)} contextPage="Executive Dashboard" />
    </AppShell>
  );
}
