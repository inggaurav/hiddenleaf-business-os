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
  CreditCard, 
  TrendingUp, 
  ArrowRight, 
  ShieldCheck, 
  Sparkles,
  FileText,
  Warehouse,
  ShoppingBag,
  Activity
} from 'lucide-react';

export default function Dashboard() {
  const { auth, tenant, metrics, stats } = usePage<any>().props;
  const user = auth?.user;
  const workspaceTitle = tenant?.workspace_title || 'no selected workspace';

  const userCount = metrics?.members ?? stats?.users ?? 0;
  const workspaceCount = metrics?.workspaces ?? stats?.workspaces ?? 0;
  const productCount = metrics?.products ?? 0;
  const openTicketCount = metrics?.open_tickets ?? stats?.tickets ?? 0;
  const roleTitle = user?.role ? String(user.role).replace('_', ' ').toUpperCase() : 'MEMBER';

  return (
    <AppShell title="Dashboard">
      <div className="space-y-8">
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
            title="Active Workspaces"
            value={workspaceCount}
            icon={<Layers className="w-5 h-5 text-purple-400" />}
            subtitle="Scoped tenant contexts"
          />

          <MetricCard
            title="Team Members"
            value={userCount}
            icon={<Users className="w-5 h-5 text-indigo-400" />}
            subtitle="Active workspace accounts"
          />

          <MetricCard
            title="Products"
            value={productCount}
            icon={<ShoppingBag className="w-5 h-5 text-emerald-400" />}
            subtitle="Workspace catalog records"
          />

          <MetricCard
            title="Open Tickets"
            value={openTicketCount}
            icon={<Activity className="w-5 h-5 text-amber-400" />}
            subtitle="Unresolved workspace requests"
          />
        </div>

        {/* Quick Launch & Operational Hub */}
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
                  <Warehouse className="w-4 h-4 text-indigo-400" />
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
