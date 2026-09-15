import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Card, CardHeader, CardBody, MetricCard } from '@/Components/UI/Card';
import { DataTable, Column, LaravelPaginator } from '@/Components/UI/DataTable';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { KanbanBoard, KanbanColumn } from '@/Components/UI/KanbanBoard';
import { formatINR, formatINRShort, formatNumber, formatPercent, formatDate } from '@/lib/format';
import { LeadPanel } from './LeadPanel';
import {
  Sparkles,
  TrendingUp,
  CheckCircle2,
  Percent,
  Plus,
  Layers3,
  FileText,
  FolderKanban,
  List,
  CheckSquare,
  ShoppingCart,
  Globe,
  Tag,
  Calendar,
  MoreHorizontal,
  Eye,
  Edit2,
  Trash2,
} from 'lucide-react';

interface Stage {
  id: number;
  pipeline_id?: number;
  name: string;
  position?: number;
}

interface Pipeline {
  id: number;
  name: string;
  is_default: boolean;
  stages?: Stage[];
}

interface CrmLead {
  id: number;
  pipeline_id?: number;
  stage_id?: number;
  name: string;
  company: string | null;
  email: string | null;
  phone: string | null;
  estimated_value: number;
  status: string;
  notes_count?: number;
  tasks_count?: number;
  completed_tasks_count?: number;
  created_at?: string;
  due_date?: string | null;
  assigned_user?: {
    id: number;
    name: string;
    email: string;
  } | null;
  stage?: Stage;
}

interface CrmDeal {
  id: number;
  name: string;
  value: number;
  status: string;
  expected_close_on?: string | null;
}

interface Props {
  leads: LaravelPaginator<CrmLead> | CrmLead[];
  allLeads?: CrmLead[];
  deals?: LaravelPaginator<CrmDeal> | CrmDeal[];
  pipelines?: Pipeline[];
  metrics?: {
    leads?: number;
    open_leads?: number;
    deals?: number;
    pipeline_value?: number;
    won_value?: number;
    conversion_rate?: number;
    stage_distribution?: Record<string | number, number>;
  };
}

const STAGE_COLORS = [
  '#8B5CF6', // purple
  '#3B82F6', // blue
  '#10B981', // green
  '#F59E0B', // amber
  '#EF4444', // red
  '#06B6D4', // cyan
  '#F97316', // orange
  '#84CC16', // lime
];

export default function CRMIndex({ leads, allLeads, deals, pipelines = [], metrics }: Props) {
  const [viewMode, setViewMode] = useState<'list' | 'kanban'>('kanban');
  const [selectedLeadId, setSelectedLeadId] = useState<number | null>(null);
  const [activePipelineId, setActivePipelineId] = useState<string>(() => {
    const def = pipelines.find((p) => p.is_default);
    if (def) return String(def.id);
    return pipelines[0]?.id ? String(pipelines[0].id) : '';
  });

  const openLeads = metrics?.open_leads ?? 0;
  const pipelineValue = metrics?.pipeline_value ?? 0;
  const wonValue = metrics?.won_value ?? 0;
  const convRate = (metrics?.conversion_rate ?? 0) / 100;

  // Active pipeline & stages
  const activePipeline = useMemo(() => {
    return pipelines.find((p) => String(p.id) === activePipelineId) || pipelines[0];
  }, [pipelines, activePipelineId]);

  const stages = activePipeline?.stages || [];

  // Group leads by stage for kanban
  const leadsList = useMemo(() => {
    return allLeads || (Array.isArray(leads) ? leads : leads.data);
  }, [allLeads, leads]);

  const cardsByStage = useMemo(() => {
    const grouped: Record<string, CrmLead[]> = {};
    stages.forEach((stage) => {
      grouped[String(stage.id)] = [];
    });
    leadsList.forEach((lead) => {
      const stageKey = String(lead.stage_id);
      if (grouped[stageKey]) {
        grouped[stageKey].push(lead);
      }
    });
    return grouped;
  }, [stages, leadsList]);

  const kanbanColumns: KanbanColumn[] = useMemo(() => {
    return stages.map((stage, i) => ({
      id: String(stage.id),
      title: stage.name,
      color: STAGE_COLORS[i % STAGE_COLORS.length],
      count: cardsByStage[String(stage.id)]?.length ?? 0,
    }));
  }, [stages, cardsByStage]);

  const handleMove = (cardId: number, toStageId: string) => {
    router.post(
      `/crm/leads/${cardId}/move`,
      { stage_id: parseInt(toStageId, 10) },
      { preserveState: true, preserveScroll: true }
    );
  };

  const columns: Column<CrmLead>[] = [
    {
      header: 'Lead Name',
      accessorKey: 'name',
      render: (row) => (
        <span className="text-sm font-medium text-[var(--text-primary)]">
          {row.name}
        </span>
      ),
    },
    {
      header: 'Company',
      accessorKey: 'company',
      render: (row) => (
        <span className="text-xs text-[var(--text-secondary)]">
          {row.company || '—'}
        </span>
      ),
    },
    {
      header: 'Contact',
      render: (row) => (
        <div className="flex flex-col text-xs text-[var(--text-secondary)]">
          <span>{row.email || '—'}</span>
          {row.phone && <span className="text-[var(--text-tertiary)]">{row.phone}</span>}
        </div>
      ),
    },
    {
      header: 'Est. Value',
      accessorKey: 'estimated_value',
      render: (row) => (
        <span className="text-xs font-semibold tabular-nums text-[var(--text-primary)]">
          {formatINR(row.estimated_value)}
        </span>
      ),
    },
    {
      header: 'Status',
      accessorKey: 'status',
      render: (row) => (
        <Badge variant={row.status === 'converted' ? 'success' : row.status === 'lost' ? 'danger' : 'neutral'}>
          {row.status}
        </Badge>
      ),
    },
    {
      header: 'Action',
      render: (row) => (
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setSelectedLeadId(row.id)}
        >
          Manage
        </Button>
      ),
    },
  ];

  const renderLeadCard = (lead: CrmLead) => {
    const tasksDone = lead.completed_tasks_count ?? 0;
    const tasksTotal = lead.tasks_count ?? 0;
    const isOverdue = lead.due_date ? new Date(lead.due_date) < new Date() : false;

    return (
      <div
        onClick={() => setSelectedLeadId(lead.id)}
        className="group relative rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-1)] p-3.5 shadow-xs transition-all hover:border-[var(--border-medium)] cursor-pointer"
      >
        {/* Top row: Name & 3-dot menu */}
        <div className="flex items-start justify-between gap-2">
          <span
            className="text-[13px] font-semibold text-[var(--text-primary)] group-hover:text-[var(--brand-primary)] transition-colors truncate flex-1"
          >
            {lead.name}
          </span>
          <div className="opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              type="button"
              className="p-1 rounded text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface-2)]"
              title="Actions"
            >
              <MoreHorizontal className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        {/* Company / description preview */}
        <p className="mt-1 text-xs text-[var(--text-secondary)] line-clamp-2">
          {lead.company || (lead.email ? `Contact: ${lead.email}` : 'Direct Prospect')}
        </p>

        {/* Value if any */}
        {lead.estimated_value > 0 && (
          <div className="mt-2 text-xs font-semibold text-emerald-400 tabular-nums">
            {formatINR(lead.estimated_value)}
          </div>
        )}

        {/* Badges row */}
        <div className="mt-3 flex items-center gap-1.5 flex-wrap">
          {tasksTotal > 0 && (
            <span
              className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium tabular-nums ${
                tasksDone === tasksTotal
                  ? 'bg-emerald-500/15 text-emerald-400'
                  : 'bg-[var(--surface-2)] text-[var(--text-secondary)]'
              }`}
            >
              <CheckSquare className="w-3 h-3" />
              {tasksDone}/{tasksTotal}
            </span>
          )}

          {lead.notes_count !== undefined && lead.notes_count > 0 && (
            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium tabular-nums bg-blue-500/15 text-blue-400">
              <FileText className="w-3 h-3" />
              {lead.notes_count}
            </span>
          )}

          <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-500/15 text-amber-400">
            <Tag className="w-3 h-3" />
            {lead.status}
          </span>
        </div>

        {/* Bottom row: Avatars + Due date */}
        <div className="mt-3 pt-2.5 border-t border-[var(--border-subtle)] flex items-center justify-between gap-2">
          <div className="flex items-center">
            {lead.assigned_user ? (
              <div
                className="w-5 h-5 rounded-full bg-[var(--surface-3)] border border-[var(--border-subtle)] flex items-center justify-center text-[10px] font-bold text-[var(--text-primary)]"
                title={lead.assigned_user.name}
              >
                {lead.assigned_user.name.charAt(0).toUpperCase()}
              </div>
            ) : (
              <span className="text-[11px] text-[var(--text-tertiary)]">Unassigned</span>
            )}
          </div>

          <div className="flex items-center gap-1 text-[11px] tabular-nums">
            <Calendar className={`w-3 h-3 ${isOverdue ? 'text-rose-400' : 'text-[var(--text-tertiary)]'}`} />
            <span className={isOverdue ? 'text-rose-400 font-medium' : 'text-[var(--text-tertiary)]'}>
              {lead.due_date ? formatDate(lead.due_date) : (lead.created_at ? formatDate(lead.created_at) : 'Active')}
            </span>
          </div>
        </div>
      </div>
    );
  };

  return (
    <AppShell title="CRM">
      <Head title="CRM — Pipelines & Leads" />
      <div className="space-y-6">
        {/* 1. SectionHeader with View Toggle */}
        <SectionHeader
          title="CRM & Pipeline"
          description="Track prospects, manage deal flow, and drive sales conversions."
          actions={
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setViewMode((v) => (v === 'list' ? 'kanban' : 'list'))}
                icon={viewMode === 'kanban' ? <List className="w-4 h-4" /> : <FolderKanban className="w-4 h-4" />}
              >
                {viewMode === 'kanban' ? 'List' : 'Kanban'}
              </Button>
              <Link href="/crm/leads">
                <Button variant="neutral" size="sm" icon={<Plus className="w-4 h-4" />}>
                  Add lead
                </Button>
              </Link>
            </div>
          }
        />

        {/* 2. Four MetricCards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Open leads"
            value={formatNumber(openLeads)}
            icon={<Sparkles className="w-4 h-4" />}
          />
          <MetricCard
            title="Pipeline value"
            value={formatINRShort(pipelineValue)}
            icon={<TrendingUp className="w-4 h-4" />}
          />
          <MetricCard
            title="Deals won MTD"
            value={formatINRShort(wonValue)}
            icon={<CheckCircle2 className="w-4 h-4" />}
          />
          <MetricCard
            title="Conversion rate"
            value={formatPercent(convRate)}
            icon={<Percent className="w-4 h-4" />}
          />
        </div>

        {/* Pipeline Selector */}
        {pipelines.length > 0 && (
          <div className="flex items-center justify-between gap-4 p-3 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)]">
            <div className="flex items-center gap-3">
              <span className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                Active Pipeline:
              </span>
              <select
                value={activePipelineId}
                onChange={(e) => setActivePipelineId(e.target.value)}
                className="bg-[var(--surface-2)] border border-[var(--border-subtle)] text-[var(--text-primary)] text-xs rounded-lg px-3 py-1.5 focus:outline-none focus:border-[var(--brand-primary)]"
              >
                {pipelines.map((p) => (
                  <option key={p.id} value={String(p.id)}>
                    {p.name} {p.is_default ? '(Default)' : ''}
                  </option>
                ))}
              </select>
            </div>
            <div className="text-xs text-[var(--text-secondary)]">
              {stages.length} stages · {leadsList.length} leads in funnel
            </div>
          </div>
        )}

        {/* 3. Primary Work Surface: Kanban vs List */}
        {viewMode === 'kanban' ? (
          <div className="space-y-3">
            <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              Leads Kanban Board
            </h2>
            {stages.length === 0 ? (
              <div className="rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-1)] p-8 text-center text-xs text-[var(--text-tertiary)]">
                No stages configured for this pipeline.
              </div>
            ) : (
              <KanbanBoard
                columns={kanbanColumns}
                cards={cardsByStage}
                onMove={handleMove}
                renderCard={renderLeadCard}
                emptyText="Drop lead here"
              />
            )}
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-3">
              <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                Recent Leads & Opportunities
              </h2>
              <DataTable
                data={leads}
                columns={columns}
                keyExtractor={(row) => row.id}
                searchPlaceholder="Search leads by name or company..."
                emptyTitle="No leads in pipeline"
                emptyDescription="Capture new leads from website forms or enter prospective clients manually."
              />
            </div>

            <div className="space-y-4">
              <Card level={0} padded={false}>
                <CardHeader title="Pipeline Health" subtitle="Funnel performance" />
                <CardBody className="space-y-3">
                  <div className="flex items-center justify-between text-xs py-1.5 border-b border-[var(--border-subtle)]">
                    <span className="text-[var(--text-secondary)]">Total pipeline deals</span>
                    <span className="font-bold text-[var(--text-primary)] tabular-nums">{metrics?.deals ?? 0}</span>
                  </div>
                  <div className="flex items-center justify-between text-xs py-1.5 border-b border-[var(--border-subtle)]">
                    <span className="text-[var(--text-secondary)]">Active pipelines</span>
                    <span className="font-bold text-[var(--text-primary)] tabular-nums">{pipelines.length}</span>
                  </div>
                  <div className="flex items-center justify-between text-xs py-1.5">
                    <span className="text-[var(--text-secondary)]">Win Rate</span>
                    <span className="font-bold text-emerald-400 tabular-nums">
                      {formatPercent(convRate)}
                    </span>
                  </div>
                </CardBody>
              </Card>

              <Card level={0} padded={false}>
                <CardHeader title="Pipelines" subtitle="Configured deal pipelines" />
                <CardBody className="space-y-2">
                  {pipelines.length === 0 ? (
                    <p className="text-xs text-[var(--text-tertiary)]">No pipelines configured yet.</p>
                  ) : (
                    pipelines.map((p) => (
                      <div key={p.id} className="flex items-center justify-between text-xs py-1 border-b border-[var(--border-subtle)] last:border-0">
                        <span className="font-medium text-[var(--text-primary)]">{p.name}</span>
                        {p.is_default && <Badge variant="neutral" size="sm">Default</Badge>}
                      </div>
                    ))
                  )}
                </CardBody>
              </Card>
            </div>
          </div>
        )}

        {/* 4. Quick Links Grid */}
        <div>
          <h2 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">
            CRM Workflows & Shortcuts
          </h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {[
              { name: 'Leads', href: '/crm/leads', icon: Sparkles, desc: 'Capture & qualification' },
              { name: 'Deals', href: '/crm/deals', icon: TrendingUp, desc: 'Opportunity stages' },
              { name: 'Pipelines', href: '/crm/pipelines', icon: Layers3, desc: 'Sales stages & funnels' },
              { name: 'Web Forms', href: '/crm/web-forms', icon: FileText, desc: 'Inbound lead forms' },
            ].map((link) => (
              <Link href={link.href} key={link.name}>
                <div className="p-4 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] spring-transition">
                  <div className="flex items-center gap-2 mb-1">
                    <link.icon className="w-4 h-4 text-[var(--text-tertiary)]" />
                    <span className="text-sm font-medium text-[var(--text-primary)]">{link.name}</span>
                  </div>
                  <div className="text-xs text-[var(--text-tertiary)]">{link.desc}</div>
                </div>
              </Link>
            ))}
          </div>
        </div>

        {/* Lead Slide-in Panel */}
        <LeadPanel
          leadId={selectedLeadId}
          isOpen={selectedLeadId !== null}
          onClose={() => setSelectedLeadId(null)}
          onLeadMutated={() => router.reload({ only: ['leads', 'allLeads', 'metrics'] })}
          pipelines={pipelines}
          canManage={true}
        />
      </div>
    </AppShell>
  );
}
