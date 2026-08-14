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
} from 'lucide-react';

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function Dashboard() {
  const { auth, tenant, metrics, stats } = usePage<any>().props;
  const user = auth?.user;
  const workspaceTitle = tenant?.workspace_title || 'Workspace';

  const userCount = metrics?.members ?? stats?.users ?? 0;
  const workspaceCount = metrics?.workspaces ?? stats?.workspaces ?? 0;
  const productCount = metrics?.products ?? 0;
  const openTicketCount = metrics?.open_tickets ?? stats?.tickets ?? 0;
  const salesTotal = metrics?.sales ?? 0;
  const purchaseTotal = metrics?.purchases ?? 0;
  const activeProjects = metrics?.active_projects ?? 0;
  const openLeads = metrics?.open_leads ?? 0;
  const todayPosSales = metrics?.today_pos_sales ?? 0;
  const roleTitle = user?.role ? String(user.role).replace('_', ' ').toUpperCase() : 'MEMBER';

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
      color: 'text-violet-400',
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
      color: 'text-purple-400',
      badge: 'Stock',
    },
    {
      title: 'Sales & Invoicing',
      description: 'Customer invoices, proposals conversion, and returns',
      href: '/sales/dashboard',
      icon: FileText,
      color: 'text-emerald-400',
      badge: 'Sales',
    },
    {
      title: 'Procurement & Purchasing',
      description: 'Supplier invoices, debit notes, and purchasing volume',
      href: '/procurement/dashboard',
      icon: Truck,
      color: 'text-rose-400',
      badge: 'Procurement',
    },
  ];

  return (
    <AppShell title="Dashboard">
      <div className="space-y-8 pb-12">
        {/* Welcome Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-[var(--border-subtle)]">
          <div className="space-y-1">
            <div className="flex items-center gap-2.5">
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-[var(--text-primary)]">
                Welcome back, {user?.name || 'User'}
              </h1>
              <Badge variant="purple" size="sm">{roleTitle}</Badge>
            </div>
            <p className="text-xs sm:text-sm text-[var(--text-secondary)]">
              Operational executive overview for <span className="font-semibold text-[var(--text-primary)]">{workspaceTitle}</span>.
            </p>
          </div>

          <div className="flex items-center gap-3">
            <Link href="/sales-invoices/create">
              <Button variant="primary" size="sm" icon={<FileText className="w-3.5 h-3.5" />}>
                New Invoice
              </Button>
            </Link>
          </div>
        </div>

        {/* Real Operational Metrics */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Total Sales Invoiced"
            value={formatCurrency(salesTotal)}
            icon={<TrendingUp className="w-5 h-5 text-emerald-400" />}
            subtitle="Posted customer sales"
          />

          <MetricCard
            title="Purchasing Volume"
            value={formatCurrency(purchaseTotal)}
            icon={<DollarSign className="w-5 h-5 text-amber-400" />}
            subtitle="Recognized vendor bills"
          />

          <MetricCard
            title="Active Projects"
            value={activeProjects}
            icon={<FolderKanban className="w-5 h-5 text-indigo-400" />}
            subtitle={`${openLeads} open sales leads`}
          />

          <MetricCard
            title="Open Tickets"
            value={openTicketCount}
            icon={<Activity className="w-5 h-5 text-sky-400" />}
            subtitle={`${userCount} workspace members`}
          />
        </div>

        {/* Module Dashboards Hub */}
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold text-[var(--text-primary)]">
                Business Module Dashboards
              </h2>
              <p className="text-xs text-[var(--text-tertiary)]">
                Dedicated operational command centers for each business unit
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {moduleDashboards.map((mod, idx) => {
              const Icon = mod.icon;
              return (
                <Link
                  key={idx}
                  href={mod.href}
                  className="group relative flex flex-col justify-between p-5 rounded-2xl bg-[var(--surface-1)] hover:bg-[var(--surface-2)] border border-[var(--border-subtle)] hover:border-violet-500/30 transition shadow-sm space-y-3"
                >
                  <div className="flex items-center justify-between">
                    <div className="p-2.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] group-hover:scale-105 transition">
                      <Icon className={`w-5 h-5 ${mod.color}`} />
                    </div>
                    <Badge variant="neutral" size="sm">{mod.badge}</Badge>
                  </div>

                  <div>
                    <h3 className="text-sm font-semibold text-[var(--text-primary)] group-hover:text-violet-400 transition flex items-center justify-between">
                      <span>{mod.title}</span>
                      <ChevronRight className="w-4 h-4 text-[var(--text-tertiary)] group-hover:translate-x-1 transition" />
                    </h3>
                    <p className="text-[11px] text-[var(--text-secondary)] mt-1 line-clamp-2">
                      {mod.description}
                    </p>
                  </div>
                </Link>
              );
            })}
          </div>
        </div>

        {/* Mr Fox Intelligence Spotlight & Operations */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Mr Fox Intelligence Spotlight */}
          <Card level={1} className="lg:col-span-2 space-y-4 border-purple-500/20 bg-purple-950/10">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-purple-900/40 border border-purple-500/30 flex items-center justify-center shadow-lg">
                  <MrFoxMark size={24} />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h2 className="text-base font-bold text-[var(--text-primary)]">
                      Mr Fox Intelligence Platform
                    </h2>
                    <Badge variant="neutral" size="sm">Disconnected / Ready</Badge>
                  </div>
                  <p className="text-xs text-[var(--text-secondary)]">
                    Autonomous workspace reasoning and execution assistant
                  </p>
                </div>
              </div>

              <Link href="/ai-agent/chat">
                <Button variant="intelligence" size="sm" icon={<Sparkles className="w-3.5 h-3.5" />}>
                  Open Assistant
                </Button>
              </Link>
            </div>

            <div className="p-4 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] space-y-2">
              <p className="text-xs text-[var(--text-secondary)] leading-relaxed">
                Connect your AI provider API key in Workspace Settings to unlock natural language operations, invoice extraction, and automated cross-workspace workflows.
              </p>
              <div className="flex items-center gap-2 pt-1">
                <Link
                  href="/settings"
                  className="text-xs font-semibold text-purple-400 hover:text-purple-300 flex items-center gap-1 spring-transition"
                >
                  <span>Configure AI Credentials</span>
                  <ArrowRight className="w-3 h-3" />
                </Link>
              </div>
            </div>
          </Card>

          {/* Quick Operations */}
          <Card level={0} className="space-y-3">
            <h2 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)]">
              Quick Operations
            </h2>

            <div className="space-y-2">
              <Link
                href="/sales-invoices"
                className="flex items-center justify-between p-3 rounded-xl bg-[var(--surface-2)] hover:bg-white/[0.04] border border-[var(--border-subtle)] spring-transition text-xs text-[var(--text-primary)]"
              >
                <div className="flex items-center gap-2.5">
                  <FileText className="w-4 h-4 text-purple-400" />
                  <span className="font-semibold">Manage Sales Invoices</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
              </Link>

              <Link
                href="/warehouses"
                className="flex items-center justify-between p-3 rounded-xl bg-[var(--surface-2)] hover:bg-white/[0.04] border border-[var(--border-subtle)] spring-transition text-xs text-[var(--text-primary)]"
              >
                <div className="flex items-center gap-2.5">
                  <Boxes className="w-4 h-4 text-indigo-400" />
                  <span className="font-semibold">Warehouse Inventory</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
              </Link>

              <Link
                href="/users"
                className="flex items-center justify-between p-3 rounded-xl bg-[var(--surface-2)] hover:bg-white/[0.04] border border-[var(--border-subtle)] spring-transition text-xs text-[var(--text-primary)]"
              >
                <div className="flex items-center gap-2.5">
                  <Users className="w-4 h-4 text-emerald-400" />
                  <span className="font-semibold">Team & Permissions</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
              </Link>
            </div>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
