import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { MrFoxMark } from '@/Components/MrFox/MrFoxMark';
import { 
  Users, 
  Layers, 
  Headphones, 
  Activity, 
  ArrowRight, 
  FileText, 
  Plus, 
  DollarSign, 
  ShieldCheck, 
  Clock,
  Inbox
} from 'lucide-react';

export default function Dashboard() {
  const { auth, tenant, stats = {}, recentLogs = [] } = usePage<any>().props;

  const usersCount = stats?.users ?? 0;
  const workspacesCount = stats?.workspaces ?? 0;
  const ticketsCount = stats?.tickets ?? 0;
  const activeContext = tenant?.workspace_title || 'Main Workspace';

  return (
    <AppShell title="Executive Dashboard">
      <div className="space-y-6">
        {/* Contextual Executive Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-purple-400">
                Workspace Operations
              </span>
              <Badge variant="purple" size="sm">
                {activeContext}
              </Badge>
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
              Welcome back, {auth?.user?.name || 'Executive'}
            </h1>
            <p className="text-xs sm:text-sm text-gray-400">
              Overview of active records, team capacity, and recent system audit events.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Link href="/sales-invoices/create">
              <Button variant="secondary" size="sm" icon={<FileText className="w-3.5 h-3.5" />}>
                New Invoice
              </Button>
            </Link>
            <Link href="/users/create">
              <Button variant="primary" size="sm" icon={<Plus className="w-3.5 h-3.5" />}>
                Invite Member
              </Button>
            </Link>
          </div>
        </div>

        {/* 4 Tabular Metric Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Team Members"
            value={usersCount}
            icon={<Users className="w-5 h-5 text-purple-400" />}
            subtitle="Active workspace accounts"
          />

          <MetricCard
            title="Active Workspaces"
            value={workspacesCount}
            icon={<Layers className="w-5 h-5 text-indigo-400" />}
            subtitle="Operational namespaces"
          />

          <MetricCard
            title="Helpdesk Tickets"
            value={ticketsCount}
            icon={<Headphones className="w-5 h-5 text-amber-400" />}
            subtitle="Customer support requests"
          />

          <MetricCard
            title="Tenant Context"
            value={activeContext}
            icon={<Activity className="w-5 h-5 text-emerald-400" />}
            subtitle="Current active boundary"
          />
        </div>

        {/* Mr Fox Intelligence Briefing Card (Level 2 Glass) */}
        <Card level={2} className="relative overflow-hidden p-6 border-purple-500/30">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center gap-3.5">
              <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500/20 via-purple-600/30 to-cyan-500/20 border border-purple-500/30 flex items-center justify-center shadow-lg">
                <MrFoxMark size={28} />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h3 className="text-base font-bold text-white tracking-tight">Mr Fox Autonomous Assistant</h3>
                  <Badge variant="purple" size="sm">Ready</Badge>
                </div>
                <p className="text-xs text-gray-300 mt-0.5">
                  Intelligence engine ready to assist with invoices, ledger reviews, and operational queries.
                </p>
              </div>
            </div>

            <Link href="/ai-agent/chat">
              <Button variant="intelligence" size="sm" icon={<ArrowRight className="w-3.5 h-3.5" />} iconPosition="right">
                Open Assistant
              </Button>
            </Link>
          </div>
        </Card>

        {/* Quick Operations & Recent Audit Ledger */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <Card level={0} className="space-y-4 lg:col-span-1">
            <h3 className="text-sm font-bold uppercase tracking-wider text-gray-400">
              Operational Shortcuts
            </h3>
            <div className="space-y-2">
              <Link href="/sales-invoices" className="block">
                <div className="p-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] border border-white/5 flex items-center justify-between text-xs font-medium text-gray-200 hover:text-white spring-transition">
                  <span className="flex items-center gap-2.5">
                    <FileText className="w-4 h-4 text-purple-400" />
                    Sales Invoices
                  </span>
                  <ArrowRight className="w-3.5 h-3.5 text-gray-500" />
                </div>
              </Link>

              <Link href="/bank-transfer" className="block">
                <div className="p-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] border border-white/5 flex items-center justify-between text-xs font-medium text-gray-200 hover:text-white spring-transition">
                  <span className="flex items-center gap-2.5">
                    <DollarSign className="w-4 h-4 text-emerald-400" />
                    Bank Transfers
                  </span>
                  <ArrowRight className="w-3.5 h-3.5 text-gray-500" />
                </div>
              </Link>

              <Link href="/helpdesk-tickets" className="block">
                <div className="p-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] border border-white/5 flex items-center justify-between text-xs font-medium text-gray-200 hover:text-white spring-transition">
                  <span className="flex items-center gap-2.5">
                    <Headphones className="w-4 h-4 text-amber-400" />
                    Support Tickets
                  </span>
                  <ArrowRight className="w-3.5 h-3.5 text-gray-500" />
                </div>
              </Link>

              <Link href="/settings" className="block">
                <div className="p-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] border border-white/5 flex items-center justify-between text-xs font-medium text-gray-200 hover:text-white spring-transition">
                  <span className="flex items-center gap-2.5">
                    <ShieldCheck className="w-4 h-4 text-indigo-400" />
                    Workspace Settings
                  </span>
                  <ArrowRight className="w-3.5 h-3.5 text-gray-500" />
                </div>
              </Link>
            </div>
          </Card>

          <Card level={0} className="space-y-4 lg:col-span-2">
            <div className="flex items-center justify-between pb-2 border-b border-white/10">
              <h3 className="text-sm font-bold uppercase tracking-wider text-gray-400">
                Recent Audit Ledger
              </h3>
              <span className="text-[11px] text-gray-500 font-mono">Real-Time</span>
            </div>

            {recentLogs && recentLogs.length > 0 ? (
              <div className="divide-y divide-white/[0.04] space-y-2">
                {recentLogs.map((log: any) => (
                  <div key={log.id} className="pt-2 flex items-start justify-between gap-3 text-xs">
                    <div className="space-y-0.5">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-white">{log.user?.name || 'System User'}</span>
                        <span className="text-[10px] text-gray-500 font-mono">({log.ip_address || '127.0.0.1'})</span>
                      </div>
                      <p className="text-gray-400">{log.action}</p>
                    </div>
                    <span className="text-[10px] text-gray-500 whitespace-nowrap">
                      {new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="py-12 text-center space-y-2">
                <Inbox className="w-8 h-8 text-gray-600 mx-auto" />
                <p className="text-xs font-semibold text-white">No recent audit events</p>
                <p className="text-[11px] text-gray-400">Activity will stream as actions are performed.</p>
              </div>
            )}
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
