import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  UserCheck,
  Target,
  Trophy,
  DollarSign,
  Percent,
  Plus,
  ArrowRight,
  Filter,
  CheckCircle2,
  Calendar,
  Activity,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ProgressDistribution } from '@/Components/UI/Charts';

interface CrmStats {
  total_leads: number;
  open_leads: number;
  converted_leads: number;
  total_deals: number;
  open_deals: number;
  won_deals: number;
  lost_deals: number;
  pipeline_value: number;
  won_value: number;
  conversion_rate: number;
}

interface Pipeline {
  id: number;
  name: string;
  is_default: boolean;
}

interface StageDistributionItem {
  stage_id: number;
  name: string;
  deals: number;
  value: number;
  is_closed: boolean;
  outcome: string | null;
}

interface LeadItem {
  id: number;
  name: string;
  email: string;
  company: string;
  estimated_value: number;
  status: string;
  created_at: string;
}

interface DealItem {
  id: number;
  name: string;
  value: number;
  stage_name: string;
  status: string;
  expected_close_on: string | null;
  created_at: string;
}

interface ActivityItem {
  id: number;
  title: string;
  type: string;
  due_at: string | null;
  created_at: string;
}

interface CrmDashboardProps {
  stats: CrmStats;
  pipelines: Pipeline[];
  activePipelineId: number | null;
  stageDistribution: StageDistributionItem[];
  recentLeads: LeadItem[];
  recentDeals: DealItem[];
  recentActivities: ActivityItem[];
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

export default function CrmDashboard({
  stats,
  pipelines = [],
  activePipelineId,
  stageDistribution = [],
  recentLeads = [],
  recentDeals = [],
  recentActivities = [],
}: CrmDashboardProps) {
  const handlePipelineChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const pipelineId = e.target.value;
    router.get('/crm/dashboard', pipelineId ? { pipeline_id: pipelineId } : {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const progressItems = stageDistribution.map((s) => ({
    name: s.name,
    value: s.deals,
    formattedValue: `${s.deals} deals (${formatCurrency(s.value)})`,
  }));

  return (
    <AppShell title="CRM Dashboard">
      <Head title="CRM & Pipeline Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="CRM & Pipeline Dashboard"
            description="Opportunity pipeline flow, deal conversion velocity, and customer acquisition metrics."
          />
          <div className="flex items-center gap-3">
            {pipelines.length > 0 && (
              <div className="flex items-center gap-2 rounded-xl bg-[var(--surface-2)] px-3 py-1.5 border border-[var(--border-subtle)]">
                <Filter className="h-4 w-4 text-[var(--text-tertiary)]" />
                <select
                  value={activePipelineId ?? ''}
                  onChange={handlePipelineChange}
                  className="bg-transparent text-xs font-semibold text-[var(--text-primary)] focus:outline-none cursor-pointer"
                >
                  <option value="" className="bg-[var(--surface-2)]">All Pipelines</option>
                  {pipelines.map((p) => (
                    <option key={p.id} value={p.id} className="bg-[var(--surface-2)]">
                      {p.name} {p.is_default ? '(Default)' : ''}
                    </option>
                  ))}
                </select>
              </div>
            )}
            <Link
              href="/crm/leads/list"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <Target className="h-4 w-4" />
              Pipeline Board
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Leads"
            value={stats.total_leads}
            icon={<UserCheck className="h-5 w-5 text-indigo-400" />}
            subtitle={`${stats.open_leads} open, ${stats.converted_leads} converted`}
          />
          <MetricCard
            title="Active Deals"
            value={stats.open_deals}
            icon={<Target className="h-5 w-5 text-sky-400" />}
            subtitle={`${stats.total_deals} lifetime deals`}
          />
          <MetricCard
            title="Pipeline Value"
            value={formatCurrency(stats.pipeline_value)}
            icon={<DollarSign className="h-5 w-5 text-emerald-400" />}
            subtitle="Open opportunity value"
          />
          <MetricCard
            title="Won Deal Value"
            value={formatCurrency(stats.won_value)}
            icon={<Trophy className="h-5 w-5 text-amber-400" />}
            trend={{
              value: `${stats.won_deals} deals won`,
              positive: true,
            }}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Lead Conversion Rate"
            value={`${stats.conversion_rate}%`}
            icon={<Percent className="h-5 w-5 text-violet-400" />}
            trend={{
              value: stats.conversion_rate >= 20 ? 'Strong Conversion' : 'Moderate',
              positive: stats.conversion_rate >= 20,
            }}
          />
          <MetricCard
            title="Won Deals"
            value={stats.won_deals}
            icon={<CheckCircle2 className="h-5 w-5 text-emerald-400" />}
            subtitle="Successfully closed contracts"
          />
          <MetricCard
            title="Lost Deals"
            value={stats.lost_deals}
            icon={<Target className="h-5 w-5 text-rose-400" />}
            subtitle="Disqualified or closed lost"
          />
          <MetricCard
            title="Converted Leads"
            value={stats.converted_leads}
            icon={<UserCheck className="h-5 w-5 text-emerald-400" />}
            subtitle="Qualified into active deals"
          />
        </div>

        {/* Pipeline Stage Distribution */}
        <Card level={0} className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Deal Pipeline Stage Distribution
              </h3>
              <p className="text-xs text-[var(--text-tertiary)]">
                Active opportunities across sales funnel stages
              </p>
            </div>
            <Badge variant="neutral">{stats.open_deals} Open Deals</Badge>
          </div>
          {stageDistribution.length === 0 ? (
            <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
              No deal stages configured for this pipeline.
            </div>
          ) : (
            <ProgressDistribution items={progressItems} />
          )}
        </Card>

        {/* Recent CRM Activity Grid */}
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Recent Leads */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Leads
              </h3>
              <Link
                href="/crm/leads/list"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentLeads.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No leads created yet.
                </div>
              ) : (
                recentLeads.map((lead) => (
                  <div key={lead.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {lead.name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {lead.company} • {lead.email}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {lead.created_at}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        {formatCurrency(lead.estimated_value)}
                      </div>
                      <Badge variant={lead.status === 'converted' ? 'success' : 'neutral'} size="sm">
                        {lead.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Deals */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Deals
              </h3>
              <Link
                href="/crm/leads/list"
                className="text-xs text-violet-400 hover:underline"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentDeals.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No deals created yet.
                </div>
              ) : (
                recentDeals.map((deal) => (
                  <div key={deal.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {deal.name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {deal.stage_name}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        Target: {deal.expected_close_on ?? 'TBD'}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        {formatCurrency(deal.value)}
                      </div>
                      <Badge
                        variant={
                          deal.status === 'won'
                            ? 'success'
                            : deal.status === 'lost'
                            ? 'danger'
                            : 'neutral'
                        }
                        size="sm"
                      >
                        {deal.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Activities */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Activities
              </h3>
              <Activity className="h-4 w-4 text-violet-400" />
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentActivities.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No CRM activities logged.
                </div>
              ) : (
                recentActivities.map((activity) => (
                  <div key={activity.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[70%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {activity.title}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {activity.created_at}
                      </div>
                    </div>
                    <Badge variant="neutral" size="sm">
                      {activity.type}
                    </Badge>
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
