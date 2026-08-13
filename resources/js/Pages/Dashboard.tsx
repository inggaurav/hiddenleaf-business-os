import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { 
  Users, 
  Layers, 
  Headphones, 
  Activity, 
  Sparkles, 
  ArrowRight, 
  FileText, 
  Plus, 
  ShieldCheck, 
  Building,
  CreditCard,
  TrendingUp,
  Clock,
  CheckCircle2,
  DollarSign
} from 'lucide-react';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';

export default function Dashboard() {
  const { auth, tenant, stats, recentLogs } = usePage<any>().props;

  const currentHour = new Date().getHours();
  const greeting = currentHour < 12 ? 'Good morning' : currentHour < 18 ? 'Good afternoon' : 'Good evening';
  const todayFormatted = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });

  return (
    <AppShell title="Executive Dashboard">
      <div className="space-y-6">
        {/* Contextual Executive Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-white/10">
          <div>
            <div className="flex items-center gap-2.5 mb-1">
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                {greeting}, {auth?.user?.name || 'Executive'}
              </h1>
              <Badge variant="purple" size="sm" dot>Live</Badge>
            </div>
            <p className="text-xs sm:text-sm text-gray-400">
              {todayFormatted} • All tenant operations and authorization boundaries verified.
            </p>
          </div>

          <div className="flex items-center gap-2.5 self-start sm:self-auto">
            <Link href="/sales-invoices/create">
              <Button size="sm" variant="primary" icon={<Plus className="w-4 h-4" />}>
                Create Invoice
              </Button>
            </Link>
            <Link href="/helpdesk-tickets/create">
              <Button size="sm" variant="secondary" icon={<Plus className="w-4 h-4" />}>
                Open Ticket
              </Button>
            </Link>
          </div>
        </div>

        {/* 4 Hero KPI Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Team & Members"
            value={stats?.users || 1}
            icon={<Users className="w-5 h-5 text-violet-400" />}
            trend={{ value: 'Active', positive: true, label: 'members assigned' }}
            subtitle="Organization capacity"
          />

          <MetricCard
            title="Active Workspaces"
            value={stats?.workspaces || 1}
            icon={<Layers className="w-5 h-5 text-indigo-400" />}
            trend={{ value: 'Scoped', neutral: true, label: 'isolated tenants' }}
            subtitle="Operational pods"
          />

          <MetricCard
            title="Helpdesk Tickets"
            value={stats?.tickets || 0}
            icon={<Headphones className="w-5 h-5 text-rose-400" />}
            trend={{ value: stats?.tickets > 0 ? 'Review' : 'Clear', positive: stats?.tickets === 0, label: 'support queue' }}
            subtitle="Customer requests"
          />

          <MetricCard
            title="Active Workspace"
            value={tenant?.workspace_title || 'Main Workspace'}
            icon={<Activity className="w-5 h-5 text-emerald-400" />}
            trend={{ value: 'Isolated', positive: true, label: 'tenant context' }}
            subtitle="RBAC policy active"
          />
        </div>

        {/* Intelligence Briefing & Attention Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Mr Fox Intelligence Briefing (Level 2 Glass) */}
          <Card level={2} className="lg:col-span-2 flex flex-col justify-between relative overflow-hidden">
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                  <div className="w-8 h-8 rounded-xl bg-gradient-to-tr from-amber-500 to-purple-600 flex items-center justify-center text-white shadow-md">
                    <Sparkles className="w-4 h-4 animate-fox-pulse" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-white tracking-tight flex items-center gap-2">
                      Mr Fox Proactive Briefing
                    </h3>
                    <p className="text-[11px] text-purple-300">Continuous business telemetry</p>
                  </div>
                </div>
                <Badge variant="purple" size="sm">Autonomous AI</Badge>
              </div>

              <p className="text-xs sm:text-sm text-gray-200 leading-relaxed pt-1">
                Your workspace is running at <strong className="text-emerald-300">100% operational integrity</strong>. Multi-tenancy boundaries and cryptographic license tokens are actively verified.
              </p>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2">
                <div className="p-3 rounded-xl bg-white/[0.04] border border-white/10 hover:border-purple-500/30 spring-transition">
                  <div className="flex items-center gap-2 text-xs font-semibold text-purple-300 mb-1">
                    <DollarSign className="w-4 h-4 text-emerald-400" />
                    <span>Billing & Approvals</span>
                  </div>
                  <p className="text-[11px] text-gray-300">Check pending bank transfer receipts and update plan allocations.</p>
                  <Link href="/bank-transfer" className="text-xs text-violet-300 hover:text-white font-medium inline-flex items-center gap-1 mt-2">
                    Review Transfers <ArrowRight className="w-3 h-3" />
                  </Link>
                </div>

                <div className="p-3 rounded-xl bg-white/[0.04] border border-white/10 hover:border-purple-500/30 spring-transition">
                  <div className="flex items-center gap-2 text-xs font-semibold text-purple-300 mb-1">
                    <Headphones className="w-4 h-4 text-rose-400" />
                    <span>Customer Helpdesk</span>
                  </div>
                  <p className="text-[11px] text-gray-300">Ensure all high-priority customer tickets receive immediate responses.</p>
                  <Link href="/helpdesk-tickets" className="text-xs text-violet-300 hover:text-white font-medium inline-flex items-center gap-1 mt-2">
                    Open Helpdesk <ArrowRight className="w-3 h-3" />
                  </Link>
                </div>
              </div>
            </div>

            <div className="pt-4 mt-4 border-t border-purple-500/20 flex items-center justify-between text-xs text-purple-300">
              <span>Press <kbd className="px-1.5 py-0.5 rounded bg-white/10 text-white font-semibold">⌘J</kbd> anytime to launch Mr Fox Assistant</span>
              <Link href="/ai-agent/chat" className="text-xs text-purple-200 hover:text-white font-semibold inline-flex items-center gap-1">
                Open AI Chat <ArrowRight className="w-3 h-3" />
              </Link>
            </div>
          </Card>

          {/* Quick Operations Panel */}
          <Card level={0} className="space-y-3">
            <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
              Quick Operations
            </h3>

            <div className="space-y-2">
              <Link
                href="/sales-invoices/create"
                className="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.03] hover:bg-white/[0.08] border border-white/10 spring-transition text-xs font-medium text-gray-200 hover:text-white group"
              >
                <div className="flex items-center gap-2.5">
                  <FileText className="w-4 h-4 text-emerald-400" />
                  <span>New Sales Invoice</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-gray-500 group-hover:text-white spring-transition" />
              </Link>

              <Link
                href="/sales-proposals/create"
                className="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.03] hover:bg-white/[0.08] border border-white/10 spring-transition text-xs font-medium text-gray-200 hover:text-white group"
              >
                <div className="flex items-center gap-2.5">
                  <FileText className="w-4 h-4 text-cyan-400" />
                  <span>Create Proposal</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-gray-500 group-hover:text-white spring-transition" />
              </Link>

              <Link
                href="/warehouses"
                className="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.03] hover:bg-white/[0.08] border border-white/10 spring-transition text-xs font-medium text-gray-200 hover:text-white group"
              >
                <div className="flex items-center gap-2.5">
                  <Building className="w-4 h-4 text-amber-400" />
                  <span>Warehouse Inventory</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-gray-500 group-hover:text-white spring-transition" />
              </Link>

              <Link
                href="/plans"
                className="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.03] hover:bg-white/[0.08] border border-white/10 spring-transition text-xs font-medium text-gray-200 hover:text-white group"
              >
                <div className="flex items-center gap-2.5">
                  <CreditCard className="w-4 h-4 text-purple-400" />
                  <span>Manage Plans</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-gray-500 group-hover:text-white spring-transition" />
              </Link>
            </div>
          </Card>
        </div>

        {/* Recent Audit Ledger / Activity Stream */}
        <Card level={0} padded={false} className="overflow-hidden">
          <div className="p-4 sm:p-5 border-b border-white/10 flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-white tracking-tight">Recent Activity Ledger</h3>
              <p className="text-xs text-gray-400 mt-0.5">Unalterable tenant security and mutation audit records</p>
            </div>
            <Link href="/users-login-history" className="text-xs text-violet-400 hover:text-violet-300 font-medium inline-flex items-center gap-1">
              Full Logs <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="divide-y divide-white/[0.06]">
            {recentLogs && recentLogs.length > 0 ? (
              recentLogs.map((log: any) => (
                <div key={log.id} className="p-4 sm:px-5 flex items-center justify-between gap-4 hover:bg-white/[0.02] spring-transition">
                  <div className="flex items-center gap-3 overflow-hidden">
                    <div className="w-8 h-8 rounded-lg bg-white/[0.04] border border-white/10 flex items-center justify-center text-violet-400 flex-shrink-0">
                      <ShieldCheck className="w-4 h-4" />
                    </div>
                    <div className="truncate">
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-white truncate">
                          {log.actor?.name || 'System'}
                        </span>
                        <StatusBadge status={log.event_type || log.action || 'info'} />
                      </div>
                      <p className="text-xs text-gray-400 truncate mt-0.5">
                        {log.action} • <span className="text-gray-500">{log.ip_address || '127.0.0.1'}</span>
                      </p>
                    </div>
                  </div>

                  <span className="text-[11px] text-gray-500 flex items-center gap-1 flex-shrink-0">
                    <Clock className="w-3 h-3" />
                    {new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </span>
                </div>
              ))
            ) : (
              <div className="p-8 text-center text-xs text-gray-500">
                No recent activity recorded for this workspace context.
              </div>
            )}
          </div>
        </Card>
      </div>
    </AppShell>
  );
}
