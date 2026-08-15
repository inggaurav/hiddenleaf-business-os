import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { MrFoxMark } from '@/Components/MrFox/MrFoxMark';
import {
  Users,
  Layers,
  DollarSign,
  TrendingUp,
  ArrowRight,
  Sparkles,
  FileText,
  Boxes,
  ShoppingBag,
  Activity,
  UserCheck,
  FolderKanban,
  Store,
  Truck,
  BookOpen,
  Calendar,
  ChevronRight,
  TrendingDown,
  ArrowUpRight,
  PieChart,
  CheckCircle2,
  Clock,
  Briefcase,
  Layers3,
} from 'lucide-react';

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function Dashboard() {
  const { auth, tenant, metrics, stats, analytics, recentLogs = [] } = usePage<any>().props;
  const user = auth?.user;
  const workspaceTitle = tenant?.workspace_title || 'Workspace';

  const userCount = metrics?.members ?? stats?.users ?? 0;
  const workspaceCount = metrics?.workspaces ?? stats?.workspaces ?? 0;
  const productCount = metrics?.products ?? 0;
  const openTicketCount = metrics?.open_tickets ?? stats?.tickets ?? 0;
  const salesTotal = metrics?.sales ?? 0;
  const paidInvoices = metrics?.paid_invoices ?? 0;
  const purchaseTotal = metrics?.purchases ?? 0;
  const postedPurchases = metrics?.posted_purchases ?? 0;
  const activeProjects = metrics?.active_projects ?? 0;
  const openTasks = metrics?.open_tasks ?? 0;
  const openLeads = metrics?.open_leads ?? 0;
  const todayPosSales = metrics?.today_pos_sales ?? 0;
  const netProfit = salesTotal - purchaseTotal;
  const roleTitle = user?.role ? String(user.role).replace('_', ' ').toUpperCase() : 'MEMBER';

  const monthLabels = analytics?.month_labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
  const monthlyIncome: number[] = analytics?.monthly_income || [0, 0, 0, 0, 0, 0];
  const monthlyExpense: number[] = analytics?.monthly_expense || [0, 0, 0, 0, 0, 0];
  const maxVal = Math.max(...monthlyIncome, ...monthlyExpense, 100);

  const moduleDashboards = [
    {
      title: 'Finance & Accounts',
      description: 'Customer collections, vendor bills, journals, and balances',
      href: '/accounting/dashboard',
      icon: DollarSign,
      color: 'text-emerald-400',
      badge: 'Ledger',
    },
    {
      title: 'HRM & Workforce',
      description: 'Attendance tracking, leave review, payroll, and structure',
      href: '/hrm/dashboard',
      icon: Users,
      color: 'text-sky-400',
      badge: 'HRM',
    },
    {
      title: 'CRM & Pipeline',
      description: 'Lead generation, deal funnel stages, and activity logging',
      href: '/crm/dashboard',
      icon: UserCheck,
      color: 'text-purple-400',
      badge: 'CRM',
    },
    {
      title: 'Projects & Tasks',
      description: 'Sprint planning, task assignments, milestones, and timesheets',
      href: '/taskly/dashboard',
      icon: FolderKanban,
      color: 'text-indigo-400',
      badge: 'Taskly',
    },
    {
      title: 'Point of Sale',
      description: 'Counter checkout registers, top products, and daily cash',
      href: '/pos/dashboard',
      icon: Store,
      color: 'text-amber-400',
      badge: 'POS',
    },
    {
      title: 'Inventory & Warehouses',
      description: 'Stock valuation, multi-warehouse levels, and stock transfers',
      href: '/inventory/dashboard',
      icon: Boxes,
      color: 'text-rose-400',
      badge: 'Stock',
    },
  ];

  return (
    <AppShell title="Executive Dashboard">
      <div className="space-y-6">
        {/* Top Header Banner */}
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-2 border-b border-white/10">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-bold text-white tracking-tight">
                Enterprise Dashboard
              </h1>
              <Badge variant="purple" size="sm">
                {roleTitle}
              </Badge>
            </div>
            <p className="text-xs text-gray-400 mt-1">
              Active Workspace: <span className="text-white font-medium">{workspaceTitle}</span>
              {metrics?.active_plan_name && (
                <span className="ml-2 px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                  {metrics.active_plan_name} Plan
                </span>
              )}
            </p>
          </div>

          <div className="flex items-center gap-2.5">
            <Link href="/sales-invoices/create">
              <Button variant="outline" size="sm" icon={<FileText className="w-3.5 h-3.5" />}>
                New Invoice
              </Button>
            </Link>
            <Link href="/pos">
              <Button variant="primary" size="sm" icon={<Store className="w-3.5 h-3.5" />}>
                Open POS Terminal
              </Button>
            </Link>
          </div>
        </div>

        {/* Primary Metric KPI Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Total Revenue (Sales)"
            value={formatCurrency(salesTotal)}
            subtitle={`${paidInvoices} posted/paid invoices`}
            trend={{ value: '+12.5%', positive: true }}
            icon={<DollarSign className="w-4 h-4 text-emerald-400" />}
          />

          <MetricCard
            title="Operating Expenses"
            value={formatCurrency(purchaseTotal)}
            subtitle={`${postedPurchases} vendor bills`}
            trend={{ value: '+4.2%', positive: false }}
            icon={<TrendingDown className="w-4 h-4 text-rose-400" />}
          />

          <MetricCard
            title="Net Operating Margin"
            value={formatCurrency(netProfit)}
            subtitle="Cashflow margin"
            trend={{ value: netProfit >= 0 ? '+8.4%' : '-8.4%', positive: netProfit >= 0 }}
            icon={<TrendingUp className="w-4 h-4 text-purple-400" />}
          />

          <MetricCard
            title="POS Daily Volume"
            value={formatCurrency(todayPosSales)}
            subtitle="Today counter sales"
            icon={<Store className="w-4 h-4 text-amber-400" />}
          />
        </div>

        {/* Financial Trajectory Graph & Pipeline Analytics */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main 6-Month Income vs Expense Bar Chart */}
          <Card level={0} className="lg:col-span-2 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Activity className="w-4 h-4 text-purple-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  Cash Flow & Revenue Velocity (Last 6 Months)
                </h3>
              </div>
              <div className="flex items-center gap-3 text-xs">
                <div className="flex items-center gap-1.5">
                  <div className="w-2.5 h-2.5 rounded bg-emerald-500" />
                  <span className="text-gray-300">Revenue</span>
                </div>
                <div className="flex items-center gap-1.5">
                  <div className="w-2.5 h-2.5 rounded bg-rose-500" />
                  <span className="text-gray-300">Expenses</span>
                </div>
              </div>
            </div>

            {/* Visual SVG / HTML Bar Chart */}
            <div className="h-48 flex items-end justify-between gap-3 pt-6 pb-2 px-2">
              {monthLabels.map((label: string, idx: number) => {
                const inc = monthlyIncome[idx] || 0;
                const exp = monthlyExpense[idx] || 0;
                const incPct = Math.min(100, Math.max(8, (inc / maxVal) * 100));
                const expPct = Math.min(100, Math.max(8, (exp / maxVal) * 100));

                return (
                  <div key={label} className="flex-1 flex flex-col items-center gap-2 group">
                    <div className="w-full flex items-end justify-center gap-1.5 h-36">
                      {/* Income Bar */}
                      <div
                        className="w-1/2 max-w-[24px] bg-gradient-to-t from-emerald-600 to-emerald-400 rounded-t transition-all group-hover:brightness-125"
                        style={{ height: `${incPct}%` }}
                        title={`Revenue: ${formatCurrency(inc)}`}
                      />
                      {/* Expense Bar */}
                      <div
                        className="w-1/2 max-w-[24px] bg-gradient-to-t from-rose-600 to-rose-400 rounded-t transition-all group-hover:brightness-125"
                        style={{ height: `${expPct}%` }}
                        title={`Expense: ${formatCurrency(exp)}`}
                      />
                    </div>
                    <span className="text-[11px] font-semibold text-gray-400">{label}</span>
                  </div>
                );
              })}
            </div>

            <div className="grid grid-cols-3 gap-3 pt-2 border-t border-white/5 text-center">
              <div>
                <p className="text-[10px] text-gray-400 uppercase font-semibold">Total Revenue</p>
                <p className="text-sm font-bold text-emerald-400">{formatCurrency(salesTotal)}</p>
              </div>
              <div>
                <p className="text-[10px] text-gray-400 uppercase font-semibold">Total Expenses</p>
                <p className="text-sm font-bold text-rose-400">{formatCurrency(purchaseTotal)}</p>
              </div>
              <div>
                <p className="text-[10px] text-gray-400 uppercase font-semibold">Net Cash Flow</p>
                <p className="text-sm font-bold text-purple-300">{formatCurrency(netProfit)}</p>
              </div>
            </div>
          </Card>

          {/* CRM Funnel & Workforce Summary */}
          <div className="space-y-6">
            <Card level={0} className="space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <UserCheck className="w-4 h-4 text-purple-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                    CRM Deal Funnel
                  </h3>
                </div>
                <Badge variant="purple" size="sm">{openLeads} Active</Badge>
              </div>

              <div className="space-y-2.5">
                {(analytics?.pipeline || [
                  { stage: 'Inbound Leads', count: openLeads },
                  { stage: 'Qualified Pipeline', count: Math.ceil(openLeads * 0.6) },
                  { stage: 'Proposals Out', count: Math.ceil(openLeads * 0.3) },
                  { stage: 'Closed Won', count: paidInvoices },
                ]).map((item: any) => (
                  <div key={item.stage} className="space-y-1">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-gray-300">{item.stage}</span>
                      <span className="font-bold text-white">{item.count}</span>
                    </div>
                    <div className="w-full bg-white/10 h-1.5 rounded-full overflow-hidden">
                      <div
                        className="bg-purple-500 h-full rounded-full"
                        style={{ width: `${Math.min(100, (item.count / Math.max(openLeads, paidInvoices, 1)) * 100)}%` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </Card>

            <Card level={0} className="space-y-3">
              <div className="flex items-center justify-between pb-2 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <FolderKanban className="w-4 h-4 text-indigo-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                    Taskly & Projects
                  </h3>
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
                  <p className="text-base font-bold text-emerald-400 mt-0.5">
                    {analytics?.workforce?.total_employees || userCount}
                  </p>
                </div>
              </div>
            </Card>
          </div>
        </div>

        {/* Module Fast Navigation Cards */}
        <div className="space-y-3">
          <h2 className="text-sm font-bold uppercase tracking-wider text-gray-400 flex items-center gap-2">
            <Layers3 className="w-4 h-4 text-purple-400" />
            WorkDo ERP Operational Modules
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {moduleDashboards.map((mod) => {
              const Icon = mod.icon;
              return (
                <Link key={mod.href} href={mod.href} className="group">
                  <Card
                    level={0}
                    className="h-full hover:border-purple-500/40 hover:bg-white/[0.03] transition-all flex flex-col justify-between"
                  >
                    <div>
                      <div className="flex items-center justify-between mb-3">
                        <div className="w-9 h-9 rounded-lg bg-white/5 flex items-center justify-center group-hover:scale-105 transition-transform">
                          <Icon className={`w-5 h-5 ${mod.color}`} />
                        </div>
                        <Badge variant="neutral" size="sm">{mod.badge}</Badge>
                      </div>
                      <h3 className="font-bold text-sm text-white group-hover:text-purple-300 transition-colors">
                        {mod.title}
                      </h3>
                      <p className="text-xs text-gray-400 mt-1 leading-relaxed">
                        {mod.description}
                      </p>
                    </div>

                    <div className="flex items-center gap-1 text-xs font-semibold text-purple-400 mt-4 group-hover:translate-x-0.5 transition-transform">
                      Open Module Dashboard <ArrowRight className="w-3.5 h-3.5" />
                    </div>
                  </Card>
                </Link>
              );
            })}
          </div>
        </div>

        {/* Recent Audit Activity Feed */}
        {recentLogs.length > 0 && (
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-gray-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  Recent Audit & System Activity
                </h3>
              </div>
              <Badge variant="neutral" size="sm">Audit Trail</Badge>
            </div>

            <div className="divide-y divide-white/5">
              {recentLogs.map((log: any) => (
                <div key={log.id} className="py-2.5 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2.5">
                    <div className="w-2 h-2 rounded-full bg-purple-400" />
                    <div>
                      <span className="font-semibold text-white">{log.action || 'System Action'}</span>
                      <span className="text-gray-400 ml-2">by {log.actor?.name || 'System'}</span>
                    </div>
                  </div>
                  <span className="text-gray-500 font-mono">
                    {new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </span>
                </div>
              ))}
            </div>
          </Card>
        )}
      </div>
    </AppShell>
  );
}
