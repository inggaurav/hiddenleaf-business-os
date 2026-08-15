import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { MrFoxMark } from '@/Components/MrFox/MrFoxMark';
import { MrFoxPanel } from '@/Components/MrFox/MrFoxPanel';
import {
  Users,
  DollarSign,
  TrendingUp,
  ArrowRight,
  Sparkles,
  FileText,
  Boxes,
  Activity,
  UserCheck,
  FolderKanban,
  Store,
  Truck,
  ShoppingCart,
  ArrowUpRight,
  Clock,
  Layers3,
} from 'lucide-react';

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
}

function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(Number(value))) {
    return '—';
  }

  return `${Number(value).toFixed(1)}%`;
}

export default function Dashboard() {
  const { auth, tenant, metrics, stats, analytics, recentLogs = [] } = usePage<any>().props;
  const user = auth?.user;
  const workspaceTitle = tenant?.workspace_title || 'Workspace';
  const enabledModules: string[] = (tenant?.modules || []).map((module: string) => module.toLowerCase());

  const [insights, setInsights] = React.useState<any[]>([]);
  const [isFoxOpen, setIsFoxOpen] = React.useState(false);
  const [foxPrompt, setFoxPrompt] = React.useState('');

  React.useEffect(() => {
    const controller = new AbortController();

    fetch('/api/v1/mr-fox/insights', { signal: controller.signal })
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (Array.isArray(data?.insights)) {
          setInsights(data.insights);
        }
      })
      .catch((error) => {
        if (error?.name !== 'AbortError') {
          setInsights([]);
        }
      });

    return () => controller.abort();
  }, []);

  const userCount = metrics?.members ?? stats?.users ?? 0;
  const salesTotal = Number(metrics?.sales ?? 0);
  const paidInvoices = Number(metrics?.paid_invoices ?? 0);
  const purchaseTotal = Number(metrics?.purchases ?? 0);
  const postedPurchases = Number(metrics?.posted_purchases ?? 0);
  const activeProjects = Number(metrics?.active_projects ?? 0);
  const openTasks = Number(metrics?.open_tasks ?? 0);
  const openLeads = Number(metrics?.open_leads ?? 0);
  const activeDeals = Number(metrics?.active_deals ?? analytics?.active_deals ?? 0);
  const todayPosSales = Number(metrics?.today_pos_sales ?? 0);
  const netOperatingResult = Number(metrics?.net_operating_result ?? (salesTotal - purchaseTotal));
  const netMarginPercent = metrics?.net_margin_percent ?? (salesTotal > 0 ? (netOperatingResult / salesTotal) * 100 : null);
  const roleTitle = user?.is_owner
    ? 'OWNER'
    : user?.role
      ? String(user.role).replaceAll('_', ' ').toUpperCase()
      : 'MEMBER';

  const monthLabels: string[] = analytics?.month_labels ?? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
  const monthlyIncome: number[] = analytics?.monthly_income ?? [0, 0, 0, 0, 0, 0];
  const monthlyExpense: number[] = analytics?.monthly_expense ?? [0, 0, 0, 0, 0, 0];
  const maxVal = Math.max(...monthlyIncome, ...monthlyExpense, 1);
  const pipeline = Array.isArray(analytics?.pipeline) ? analytics.pipeline : [];
  const pipelineMax = Math.max(...pipeline.map((item: any) => Number(item?.count ?? 0)), 1);
  const workforceCount = Number(analytics?.workforce?.total_employees ?? 0);

  const moduleDashboards = [
    {
      module: 'account',
      title: 'Finance & Accounts',
      description: 'Customer collections, vendor bills, journals, balances, and financial reports',
      href: '/accounting/dashboard',
      icon: DollarSign,
      color: 'text-emerald-400',
      badge: 'Ledger',
    },
    {
      module: 'sales',
      title: 'Sales & Invoicing',
      description: 'Invoices, proposals, returns, collections, and customer revenue workflows',
      href: '/sales/dashboard',
      icon: ShoppingCart,
      color: 'text-violet-400',
      badge: 'Sales',
    },
    {
      module: 'procurement',
      title: 'Procurement',
      description: 'Vendor bills, purchase returns, supplier purchasing, and payable workflows',
      href: '/procurement/dashboard',
      icon: Truck,
      color: 'text-orange-400',
      badge: 'Buy',
    },
    {
      module: 'hrm',
      title: 'HRM & Workforce',
      description: 'Attendance tracking, leave review, payroll, employees, and organization structure',
      href: '/hrm/dashboard',
      icon: Users,
      color: 'text-sky-400',
      badge: 'HRM',
    },
    {
      module: 'lead',
      title: 'CRM & Pipeline',
      description: 'Lead generation, qualification, deal stages, notes, and activity logging',
      href: '/crm/dashboard',
      icon: UserCheck,
      color: 'text-purple-400',
      badge: 'CRM',
    },
    {
      module: 'taskly',
      title: 'Projects & Tasks',
      description: 'Sprint planning, task assignments, milestones, timesheets, and project delivery',
      href: '/taskly/dashboard',
      icon: FolderKanban,
      color: 'text-indigo-400',
      badge: 'Taskly',
    },
    {
      module: 'pos',
      title: 'Point of Sale',
      description: 'Counter checkout, billing counters, orders, discounts, returns, and daily cash',
      href: '/pos/dashboard',
      icon: Store,
      color: 'text-amber-400',
      badge: 'POS',
    },
    {
      module: 'productservice',
      title: 'Inventory & Warehouses',
      description: 'Products, stock valuation, warehouse levels, adjustments, and stock transfers',
      href: '/inventory/dashboard',
      icon: Boxes,
      color: 'text-rose-400',
      badge: 'Stock',
    },
  ].filter((module) => enabledModules.length === 0 || enabledModules.includes(module.module));

  return (
    <AppShell title="Executive Dashboard">
      <div className="space-y-5 sm:space-y-6">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-white/10">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-xl font-bold text-white tracking-tight">Enterprise Dashboard</h1>
              <Badge variant="purple" size="sm">{roleTitle}</Badge>
            </div>
            <p className="text-xs text-gray-400 mt-1 flex flex-wrap items-center gap-1.5">
              <span>Active Workspace:</span>
              <span className="text-white font-medium">{workspaceTitle}</span>
              {metrics?.active_plan_name && (
                <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                  {metrics.active_plan_name} Plan
                </span>
              )}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2.5">
            {enabledModules.includes('sales') && (
              <Link href="/sales-invoices/create">
                <Button variant="outline" size="sm" icon={<FileText className="w-3.5 h-3.5" />}>
                  New Invoice
                </Button>
              </Link>
            )}
            {enabledModules.includes('pos') && (
              <Link href="/pos/terminal">
                <Button variant="primary" size="sm" icon={<Store className="w-3.5 h-3.5" />}>
                  Open POS Terminal
                </Button>
              </Link>
            )}
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <MetricCard
            title="Recognized Sales"
            value={formatCurrency(salesTotal)}
            subtitle={`${paidInvoices} paid invoice${paidInvoices === 1 ? '' : 's'}`}
            icon={<DollarSign className="w-4 h-4 text-emerald-400" />}
          />

          <MetricCard
            title="Operating Purchases"
            value={formatCurrency(purchaseTotal)}
            subtitle={`${postedPurchases} posted vendor bill${postedPurchases === 1 ? '' : 's'}`}
            icon={<Truck className="w-4 h-4 text-rose-400" />}
          />

          <MetricCard
            title="Net Operating Margin"
            value={formatPercent(netMarginPercent)}
            subtitle={`Net operating result ${formatCurrency(netOperatingResult)}`}
            icon={<TrendingUp className="w-4 h-4 text-purple-400" />}
          />

          <MetricCard
            title="POS Sales Today"
            value={formatCurrency(todayPosSales)}
            subtitle="Completed counter sales today"
            icon={<Store className="w-4 h-4 text-amber-400" />}
          />
        </div>

        {insights.length > 0 && (
          <Card level={1} className="border-purple-500/30 bg-purple-950/10 space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-purple-500/20">
              <div className="flex items-start sm:items-center gap-2.5 min-w-0">
                <div className="w-7 h-7 rounded-lg bg-purple-900/40 border border-purple-500/30 flex items-center justify-center flex-shrink-0">
                  <MrFoxMark size={16} />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-white tracking-tight flex flex-wrap items-center gap-2">
                    Mr. Fox Executive Intelligence & Signals
                    <Badge variant="purple" size="sm">Grounded Signals</Badge>
                  </h3>
                </div>
              </div>
              <Button
                variant="intelligence"
                size="sm"
                onClick={() => {
                  setFoxPrompt('Review all outstanding business risks and recommendations');
                  setIsFoxOpen(true);
                }}
                icon={<Sparkles className="w-3.5 h-3.5" />}
              >
                Ask Mr. Fox
              </Button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5">
              {insights.map((insight: any) => (
                <div
                  key={insight.id}
                  className="p-3 rounded-xl bg-purple-950/20 border border-purple-500/20 space-y-2 flex flex-col justify-between"
                >
                  <div className="space-y-1">
                    <div className="flex items-start justify-between gap-3">
                      <span className="text-xs font-semibold text-purple-200">{insight.title}</span>
                      <span
                        className={`text-[10px] uppercase font-bold px-1.5 py-0.5 rounded flex-shrink-0 ${
                          insight.severity === 'warning'
                            ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                            : insight.severity === 'positive'
                              ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                              : 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                        }`}
                      >
                        {insight.severity}
                      </span>
                    </div>
                    <p className="text-xs text-gray-300 leading-relaxed">{insight.summary}</p>
                  </div>

                  {Array.isArray(insight.evidence) && insight.evidence.length > 0 && (
                    <div className="pt-2 border-t border-purple-500/10 space-y-1">
                      <div className="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Evidence Sources</div>
                      <div className="flex flex-wrap gap-1">
                        {insight.evidence.slice(0, 3).map((ev: any, idx: number) => (
                          <Link
                            key={`${insight.id}-${idx}`}
                            href={ev.route || '#'}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] bg-purple-900/30 hover:bg-purple-900/50 border border-purple-500/20 text-purple-300 transition-colors"
                          >
                            <span className="max-w-[220px] truncate">{ev.label}</span>
                            <ArrowUpRight className="w-2.5 h-2.5 text-gray-400 flex-shrink-0" />
                          </Link>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </Card>
        )}

        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <Card level={0} className="xl:col-span-2 space-y-4 overflow-hidden">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Activity className="w-4 h-4 text-purple-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">Revenue vs Purchases — Last 6 Months</h3>
              </div>
              <div className="flex items-center gap-3 text-xs">
                <div className="flex items-center gap-1.5"><div className="w-2.5 h-2.5 rounded bg-emerald-500" /><span className="text-gray-300">Sales</span></div>
                <div className="flex items-center gap-1.5"><div className="w-2.5 h-2.5 rounded bg-rose-500" /><span className="text-gray-300">Purchases</span></div>
              </div>
            </div>

            <div className="h-48 flex items-end justify-between gap-2 sm:gap-3 pt-6 pb-2 px-1 sm:px-2">
              {monthLabels.map((label: string, idx: number) => {
                const income = Number(monthlyIncome[idx] ?? 0);
                const expense = Number(monthlyExpense[idx] ?? 0);
                const incomePct = income <= 0 ? 0 : Math.min(100, Math.max(6, (income / maxVal) * 100));
                const expensePct = expense <= 0 ? 0 : Math.min(100, Math.max(6, (expense / maxVal) * 100));

                return (
                  <div key={`${label}-${idx}`} className="flex-1 min-w-0 flex flex-col items-center gap-2 group">
                    <div className="w-full flex items-end justify-center gap-1 h-36">
                      <div
                        className="w-1/2 max-w-[24px] bg-gradient-to-t from-emerald-600 to-emerald-400 rounded-t transition-all group-hover:brightness-125"
                        style={{ height: `${incomePct}%` }}
                        title={`Sales: ${formatCurrency(income)}`}
                      />
                      <div
                        className="w-1/2 max-w-[24px] bg-gradient-to-t from-rose-600 to-rose-400 rounded-t transition-all group-hover:brightness-125"
                        style={{ height: `${expensePct}%` }}
                        title={`Purchases: ${formatCurrency(expense)}`}
                      />
                    </div>
                    <span className="text-[10px] sm:text-[11px] font-semibold text-gray-400">{label}</span>
                  </div>
                );
              })}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-white/5 text-center">
              <div><p className="text-[10px] text-gray-400 uppercase font-semibold">Total Sales</p><p className="text-sm font-bold text-emerald-400">{formatCurrency(salesTotal)}</p></div>
              <div><p className="text-[10px] text-gray-400 uppercase font-semibold">Total Purchases</p><p className="text-sm font-bold text-rose-400">{formatCurrency(purchaseTotal)}</p></div>
              <div><p className="text-[10px] text-gray-400 uppercase font-semibold">Net Operating Result</p><p className="text-sm font-bold text-purple-300">{formatCurrency(netOperatingResult)}</p></div>
            </div>
          </Card>

          <div className="space-y-6">
            <Card level={0} className="space-y-4">
              <div className="flex items-center justify-between gap-3 pb-2 border-b border-white/10">
                <div className="flex items-center gap-2 min-w-0">
                  <UserCheck className="w-4 h-4 text-purple-400 flex-shrink-0" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider truncate">CRM Lead Funnel</h3>
                </div>
                <Badge variant="purple" size="sm">{openLeads} Active</Badge>
              </div>

              <div className="space-y-2.5">
                {pipeline.map((item: any) => {
                  const count = Number(item?.count ?? 0);
                  return (
                    <div key={item.stage} className="space-y-1">
                      <div className="flex items-center justify-between text-xs gap-3">
                        <span className="text-gray-300 truncate">{item.stage}</span>
                        <span className="font-bold text-white flex-shrink-0">{count}</span>
                      </div>
                      <div className="w-full bg-white/10 h-1.5 rounded-full overflow-hidden">
                        <div
                          className="bg-purple-500 h-full rounded-full"
                          style={{ width: count <= 0 ? '0%' : `${Math.min(100, (count / pipelineMax) * 100)}%` }}
                        />
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="pt-2 border-t border-white/5 flex items-center justify-between text-xs">
                <span className="text-gray-400">Open deals</span>
                <span className="font-semibold text-purple-300">{activeDeals}</span>
              </div>
            </Card>

            <Card level={0} className="space-y-3">
              <div className="flex items-center justify-between pb-2 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <FolderKanban className="w-4 h-4 text-indigo-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Taskly & Projects</h3>
                </div>
                <Badge variant="neutral" size="sm">{activeProjects} Projects</Badge>
              </div>

              <div className="grid grid-cols-2 gap-3 text-center">
                <div className="p-2.5 rounded-lg bg-white/5">
                  <p className="text-xs text-gray-400">Open Tasks</p>
                  <p className="text-base font-bold text-white mt-0.5">{openTasks}</p>
                </div>
                <div className="p-2.5 rounded-lg bg-white/5">
                  <p className="text-xs text-gray-400">Employees</p>
                  <p className="text-base font-bold text-emerald-400 mt-0.5">{workforceCount}</p>
                </div>
              </div>
            </Card>
          </div>
        </div>

        <div className="space-y-3">
          <h2 className="text-sm font-bold uppercase tracking-wider text-gray-400 flex items-center gap-2">
            <Layers3 className="w-4 h-4 text-purple-400" />
            Enabled Business Modules
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {moduleDashboards.map((module) => {
              const Icon = module.icon;
              return (
                <Link key={module.href} href={module.href} className="group">
                  <Card level={0} className="h-full hover:border-purple-500/40 hover:bg-white/[0.03] transition-all flex flex-col justify-between">
                    <div>
                      <div className="flex items-center justify-between mb-3">
                        <div className="w-9 h-9 rounded-lg bg-white/5 flex items-center justify-center group-hover:scale-105 transition-transform">
                          <Icon className={`w-5 h-5 ${module.color}`} />
                        </div>
                        <Badge variant="neutral" size="sm">{module.badge}</Badge>
                      </div>
                      <h3 className="font-bold text-sm text-white group-hover:text-purple-300 transition-colors">{module.title}</h3>
                      <p className="text-xs text-gray-400 mt-1 leading-relaxed">{module.description}</p>
                    </div>
                    <div className="flex items-center gap-1 text-xs font-semibold text-purple-400 mt-4 group-hover:translate-x-0.5 transition-transform">
                      Open Module <ArrowRight className="w-3.5 h-3.5" />
                    </div>
                  </Card>
                </Link>
              );
            })}
          </div>
        </div>

        {recentLogs.length > 0 && (
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-gray-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">Recent Audit & System Activity</h3>
              </div>
              <Badge variant="neutral" size="sm">Audit Trail</Badge>
            </div>

            <div className="divide-y divide-white/5">
              {recentLogs.map((log: any) => (
                <div key={log.id} className="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs">
                  <div className="flex items-center gap-2.5 min-w-0">
                    <div className="w-2 h-2 rounded-full bg-purple-400 flex-shrink-0" />
                    <div className="truncate">
                      <span className="font-semibold text-white">{log.action || 'System Action'}</span>
                      <span className="text-gray-400 ml-2">by {log.actor?.name || 'System'}</span>
                    </div>
                  </div>
                  <span className="text-gray-500 font-mono flex-shrink-0">
                    {new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </span>
                </div>
              ))}
            </div>
          </Card>
        )}
      </div>

      <MrFoxPanel
        isOpen={isFoxOpen}
        onClose={() => setIsFoxOpen(false)}
        initialPrompt={foxPrompt}
        contextPage="Executive Dashboard"
      />
    </AppShell>
  );
}
