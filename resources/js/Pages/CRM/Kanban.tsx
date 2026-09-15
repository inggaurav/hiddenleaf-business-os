import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import {
  Plus,
  Search,
  Filter,
  SlidersHorizontal,
  Building,
  Calendar,
  Layers,
  Clock,
  ArrowRight,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Modal } from '@/Components/UI/Modal';
import { formatINRShort, formatINR, formatRelative } from '@/lib/format';
import { DealPanel } from './DealPanel';

export interface DealItem {
  id: number;
  name: string;
  value: number;
  stage_id: number;
  pipeline_id: number;
  lead_id?: number | null;
  position: number;
  probability: number;
  status: string;
  source?: string | null;
  expected_close_date?: string | null;
  actual_close_date?: string | null;
  lost_reason?: string | null;
  created_at: string;
  customer?: string;
  assigned_user?: {
    id: number;
    name: string;
    email: string;
  } | null;
  next_activity?: {
    type: string;
    subject: string;
    due_date: string;
  } | null;
}

export interface StageItem {
  id: number;
  name: string;
  position: number;
  probability: number;
  is_closed: boolean;
  outcome?: string | null;
  deals_count: number;
  total_value: number;
  deals: DealItem[];
}

export interface PipelineItem {
  id: number;
  name: string;
  is_default: boolean;
}

export interface TeamMember {
  id: number;
  name: string;
  email: string;
}

export interface LeadOption {
  id: number;
  name: string;
  company?: string | null;
}

interface KanbanProps {
  pipelines: PipelineItem[];
  activePipeline: { id: number; name: string } | null;
  stages: StageItem[];
  teamMembers: TeamMember[];
  leads: LeadOption[];
  canManage: boolean;
}

export default function Kanban({
  pipelines = [],
  activePipeline,
  stages = [],
  teamMembers = [],
  leads = [],
  canManage,
}: KanbanProps) {
  // Local stage state for optimistic drag & drop
  const [boardStages, setBoardStages] = useState<StageItem[]>(stages);

  // Sync boardStages when server stages prop updates
  React.useEffect(() => {
    setBoardStages(stages);
  }, [stages]);

  // Selected deal for slide-in drawer
  const [selectedDealId, setSelectedDealId] = useState<number | null>(null);

  // Filters
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedAssignee, setSelectedAssignee] = useState<string>('all');
  const [minValue, setMinValue] = useState<number>(0);

  // Create Deal Modal
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [createName, setCreateName] = useState('');
  const [createValue, setCreateValue] = useState('');
  const [createStageId, setCreateStageId] = useState<string>(stages[0]?.id?.toString() || '');
  const [createLeadId, setCreateLeadId] = useState('');
  const [createAssignedTo, setCreateAssignedTo] = useState('');
  const [createExpectedClose, setCreateExpectedClose] = useState('');
  const [createSource, setCreateSource] = useState('inbound');
  const [createSubmitting, setCreateSubmitting] = useState(false);

  // Drag and drop state
  const [draggedDeal, setDraggedDeal] = useState<{ dealId: number; sourceStageId: number } | null>(null);
  const [dragOverStageId, setDragOverStageId] = useState<number | null>(null);

  // Card age helper: green <7d, amber <30d, rose >30d
  const getDealAge = (createdAt: string) => {
    const diffDays = Math.max(0, Math.floor((Date.now() - new Date(createdAt).getTime()) / 86400000));
    if (diffDays < 7) {
      return {
        borderClass: 'border-l-4 border-l-emerald-500',
        badgeClass: 'text-emerald-400 bg-emerald-500/10',
        text: `${diffDays}d in stage`,
      };
    } else if (diffDays < 30) {
      return {
        borderClass: 'border-l-4 border-l-amber-500',
        badgeClass: 'text-amber-400 bg-amber-500/10',
        text: `${diffDays}d in stage`,
      };
    } else {
      return {
        borderClass: 'border-l-4 border-l-rose-500',
        badgeClass: 'text-rose-400 bg-rose-500/10',
        text: `${diffDays}d in stage`,
      };
    }
  };

  // Pipeline switcher
  const handlePipelineSelect = (pipelineId: number) => {
    router.get(
      '/crm/kanban',
      { pipeline_id: pipelineId },
      { preserveState: true, preserveScroll: true }
    );
  };

  // Drag start
  const handleDragStart = (e: React.DragEvent, dealId: number, sourceStageId: number) => {
    setDraggedDeal({ dealId, sourceStageId });
    e.dataTransfer.setData('text/plain', JSON.stringify({ dealId, sourceStageId }));
    e.dataTransfer.effectAllowed = 'move';
  };

  // Drag over stage column
  const handleDragOver = (e: React.DragEvent, stageId: number) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (dragOverStageId !== stageId) {
      setDragOverStageId(stageId);
    }
  };

  // Drop card on target stage column
  const handleDrop = (e: React.DragEvent, targetStageId: number) => {
    e.preventDefault();
    setDragOverStageId(null);

    let info = draggedDeal;
    if (!info) {
      try {
        info = JSON.parse(e.dataTransfer.getData('text/plain'));
      } catch (err) {
        return;
      }
    }
    if (!info) return;

    const { dealId, sourceStageId } = info;
    setDraggedDeal(null);

    // Optimistic UI state update
    let movedDeal: DealItem | null = null;
    const nextStages = boardStages.map((st) => {
      if (st.id === sourceStageId) {
        const remaining = st.deals.filter((d) => {
          if (d.id === dealId) {
            movedDeal = { ...d, stage_id: targetStageId };
            return false;
          }
          return true;
        });
        return {
          ...st,
          deals: remaining,
          deals_count: remaining.length,
          total_value: remaining.reduce((acc, curr) => acc + (Number(curr.value) || 0), 0),
        };
      }
      return st;
    });

    if (movedDeal) {
      const finalStages = nextStages.map((st) => {
        if (st.id === targetStageId) {
          const newDeals = [...st.deals, movedDeal!];
          return {
            ...st,
            deals: newDeals,
            deals_count: newDeals.length,
            total_value: newDeals.reduce((acc, curr) => acc + (Number(curr.value) || 0), 0),
          };
        }
        return st;
      });

      setBoardStages(finalStages);

      // Server API call: reorder/move
      const targetStage = boardStages.find((s) => s.id === targetStageId);
      const targetStageDeals = targetStage ? [...targetStage.deals.map((d) => d.id), dealId] : [dealId];

      router.post(
        '/crm/deals/reorder',
        {
          stage_id: targetStageId,
          ordered_ids: targetStageDeals,
        },
        {
          preserveScroll: true,
          preserveState: true,
          onError: () => {
            // Rollback on error
            setBoardStages(stages);
          },
        }
      );
    }
  };

  // Filtered stages and deals
  const filteredStages = useMemo(() => {
    return boardStages.map((st) => {
      const filteredDeals = st.deals.filter((d) => {
        // Search query
        if (searchQuery.trim()) {
          const q = searchQuery.toLowerCase();
          const matchTitle = d.name.toLowerCase().includes(q);
          const matchCustomer = d.customer?.toLowerCase().includes(q);
          if (!matchTitle && !matchCustomer) return false;
        }

        // Assignee filter
        if (selectedAssignee !== 'all') {
          if (String(d.assigned_user?.id) !== selectedAssignee) return false;
        }

        // Min value filter
        if (minValue > 0 && d.value < minValue) {
          return false;
        }

        return true;
      });

      return {
        ...st,
        filteredDeals,
        filteredCount: filteredDeals.length,
        filteredValue: filteredDeals.reduce((sum, d) => sum + (Number(d.value) || 0), 0),
      };
    });
  }, [boardStages, searchQuery, selectedAssignee, minValue]);

  const handleCreateDeal = (e: React.FormEvent) => {
    e.preventDefault();
    if (!createName.trim() || !createValue) return;
    setCreateSubmitting(true);

    router.post(
      '/crm/deals',
      {
        name: createName,
        value: parseFloat(createValue),
        pipeline_id: activePipeline?.id,
        stage_id: createStageId ? parseInt(createStageId, 10) : undefined,
        lead_id: createLeadId ? parseInt(createLeadId, 10) : undefined,
        assigned_to: createAssignedTo ? parseInt(createAssignedTo, 10) : undefined,
        expected_close_date: createExpectedClose || undefined,
        source: createSource,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setCreateSubmitting(false);
          setShowCreateModal(false);
          setCreateName('');
          setCreateValue('');
          setCreateExpectedClose('');
          router.reload({ only: ['stages'] });
        },
        onError: () => setCreateSubmitting(false),
      }
    );
  };

  return (
    <AppShell title="Deals Kanban">
      <Head title="Deals Kanban — CRM" />

      <div className="space-y-6 pb-12">
        {/* Top Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <h1 className="text-xl font-bold tracking-tight text-[var(--text-primary)] flex items-center gap-2">
              <Layers className="h-5 w-5 text-[var(--brand-primary)]" />
              CRM Deals Kanban
            </h1>
            <p className="text-xs text-[var(--text-secondary)]">
              Visual pipeline execution: track deal probability, progression, and revenue velocity.
            </p>
          </div>

          <div className="flex items-center gap-3">
            {/* Pipeline Dropdown */}
            {pipelines.length > 1 && (
              <select
                value={activePipeline?.id}
                onChange={(e) => handlePipelineSelect(Number(e.target.value))}
                className="bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] px-3 py-2 focus:outline-none cursor-pointer"
              >
                {pipelines.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name} {p.is_default ? '(Default)' : ''}
                  </option>
                ))}
              </select>
            )}

            <Button
              variant="neutral"
              icon={<Plus className="h-4 w-4" />}
              onClick={() => {
                if (stages[0]) setCreateStageId(stages[0].id.toString());
                setShowCreateModal(true);
              }}
            >
              New Deal
            </Button>
          </div>
        </div>

        {/* Filter Bar */}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-4 p-3 rounded-2xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-xs">
          {/* Search */}
          <div className="relative">
            <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-[var(--text-tertiary)]" />
            <input
              type="text"
              placeholder="Search deals or accounts..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-subtle)] pl-8 pr-3 py-1.5 focus:outline-none placeholder:text-[var(--text-tertiary)]"
            />
          </div>

          {/* Assignee Filter */}
          <div className="flex items-center gap-2">
            <Filter className="h-3.5 w-3.5 text-[var(--text-tertiary)] shrink-0" />
            <select
              value={selectedAssignee}
              onChange={(e) => setSelectedAssignee(e.target.value)}
              className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-subtle)] px-2.5 py-1.5 focus:outline-none cursor-pointer"
            >
              <option value="all">All Assignees</option>
              {teamMembers.map((m) => (
                <option key={m.id} value={String(m.id)}>
                  {m.name}
                </option>
              ))}
            </select>
          </div>

          {/* Min Value Slider */}
          <div className="sm:col-span-2 flex items-center gap-3 px-2">
            <SlidersHorizontal className="h-3.5 w-3.5 text-[var(--text-tertiary)] shrink-0" />
            <span className="text-[var(--text-tertiary)] whitespace-nowrap">
              Min: {minValue === 0 ? '₹0' : formatINRShort(minValue)}
            </span>
            <input
              type="range"
              min="0"
              max="1000000"
              step="25000"
              value={minValue}
              onChange={(e) => setMinValue(Number(e.target.value))}
              className="w-full accent-[var(--brand-primary)] h-1.5 bg-[var(--surface-3)] rounded-lg cursor-pointer"
            />
            {minValue > 0 && (
              <button
                onClick={() => setMinValue(0)}
                className="text-[10px] text-[var(--brand-primary)] hover:underline cursor-pointer"
              >
                Reset
              </button>
            )}
          </div>
        </div>

        {/* Kanban Board Horizontal Scroll */}
        <div className="flex gap-4 overflow-x-auto pb-6 pt-1 select-none min-h-[580px] scrollbar-thin">
          {filteredStages.map((stage) => {
            const isDropTarget = dragOverStageId === stage.id;
            return (
              <div
                key={stage.id}
                onDragOver={(e) => handleDragOver(e, stage.id)}
                onDrop={(e) => handleDrop(e, stage.id)}
                className={`w-80 shrink-0 flex flex-col rounded-2xl bg-[var(--surface-1)] border transition-all ${
                  isDropTarget
                    ? 'border-[var(--brand-primary)] bg-[var(--surface-2)] shadow-lg'
                    : 'border-[var(--border-subtle)]'
                }`}
              >
                {/* Stage Header */}
                <div className="p-3.5 border-b border-[var(--border-subtle)] flex items-center justify-between bg-[var(--surface-2)] rounded-t-2xl">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold text-[var(--text-primary)]">
                      {stage.name}
                    </span>
                    <span className="px-2 py-0.5 rounded-full text-[10px] font-medium bg-[var(--surface-3)] text-[var(--text-secondary)]">
                      {stage.filteredCount}
                    </span>
                  </div>
                  <div className="text-right">
                    <span className="text-[11px] font-medium text-[var(--text-tertiary)] tabular-nums">
                      {formatINRShort(stage.filteredValue)}
                    </span>
                  </div>
                </div>

                {/* Stage Deals List */}
                <div className="flex-1 p-3 space-y-3 overflow-y-auto max-h-[640px]">
                  {stage.filteredDeals.length === 0 ? (
                    <div className="h-32 flex flex-col items-center justify-center border-2 border-dashed border-[var(--border-subtle)] rounded-xl text-center p-3">
                      <p className="text-[11px] text-[var(--text-tertiary)]">Drop deal here</p>
                    </div>
                  ) : (
                    stage.filteredDeals.map((deal) => {
                      const ageInfo = getDealAge(deal.created_at);
                      return (
                        <div
                          key={deal.id}
                          draggable
                          onDragStart={(e) => handleDragStart(e, deal.id, stage.id)}
                          onClick={() => setSelectedDealId(deal.id)}
                          className={`group p-3.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] hover:shadow-md transition-all cursor-pointer space-y-2.5 ${ageInfo.borderClass}`}
                        >
                          {/* Top: Deal Name & Stage Age indicator */}
                          <div className="flex items-start justify-between gap-2">
                            <h3 className="text-xs font-semibold text-[var(--text-primary)] group-hover:text-[var(--brand-primary)] transition-colors line-clamp-2">
                              {deal.name}
                            </h3>
                            <span className={`shrink-0 px-1.5 py-0.5 rounded text-[9px] font-medium ${ageInfo.badgeClass}`}>
                              {ageInfo.text}
                            </span>
                          </div>

                          {/* Contact / Lead / Account */}
                          {deal.customer && (
                            <div className="flex items-center gap-1.5 text-[11px] text-[var(--text-secondary)] truncate">
                              <Building className="h-3 w-3 text-[var(--text-tertiary)] shrink-0" />
                              <span className="truncate">{deal.customer}</span>
                            </div>
                          )}

                          {/* Value in green & Probability */}
                          <div className="flex items-center justify-between pt-1">
                            <span className="text-sm font-semibold text-emerald-400 tabular-nums">
                              {formatINR(deal.value)}
                            </span>
                            <span className="text-[10px] px-2 py-0.5 rounded-md bg-[var(--surface-3)] text-[var(--text-secondary)] font-medium">
                              {deal.probability}%
                            </span>
                          </div>

                          {/* Footer: Next Activity & Assignee */}
                          <div className="flex items-center justify-between pt-2 border-t border-[var(--border-subtle)] text-[10px] text-[var(--text-tertiary)]">
                            {deal.next_activity ? (
                              <span className="flex items-center gap-1 text-amber-400 truncate max-w-[65%]">
                                <Clock className="h-3 w-3 shrink-0" />
                                <span className="truncate">{formatRelative(deal.next_activity.due_date)}</span>
                              </span>
                            ) : (
                              <span className="flex items-center gap-1">
                                <Calendar className="h-3 w-3 shrink-0" />
                                <span>{deal.expected_close_date ? formatRelative(deal.expected_close_date) : 'No due date'}</span>
                              </span>
                            )}

                            {/* Assigned avatar / initial */}
                            <div
                              className="h-5 w-5 rounded-full bg-[var(--surface-3)] border border-[var(--border-subtle)] flex items-center justify-center text-[9px] font-bold text-[var(--text-secondary)]"
                              title={deal.assigned_user?.name || 'Unassigned'}
                            >
                              {deal.assigned_user ? deal.assigned_user.name.charAt(0).toUpperCase() : '?'}
                            </div>
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Slide-in Detail Panel (520px) */}
      <DealPanel
        dealId={selectedDealId}
        isOpen={selectedDealId !== null}
        onClose={() => setSelectedDealId(null)}
        onDealMutated={() => router.reload({ only: ['stages'] })}
        pipelines={pipelines}
        stages={stages}
        teamMembers={teamMembers}
        canManage={canManage}
      />

      {/* New Deal Modal */}
      <Modal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        title="Create New Deal"
        description="Add a new potential deal to the active CRM sales pipeline."
        maxWidth="lg"
      >
        <form onSubmit={handleCreateDeal} className="space-y-4 pt-2">
          <div className="space-y-1">
            <label className="block text-xs font-medium text-[var(--text-secondary)]">
              Deal Title *
            </label>
            <input
              type="text"
              required
              placeholder="e.g. Enterprise Cloud Annual Contract"
              value={createName}
              onChange={(e) => setCreateName(e.target.value)}
              className="w-full bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-2.5 focus:outline-none placeholder:text-[var(--text-tertiary)]"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="block text-xs font-medium text-[var(--text-secondary)]">
                Value (INR) *
              </label>
              <input
                type="number"
                min="0"
                step="500"
                required
                placeholder="250000"
                value={createValue}
                onChange={(e) => setCreateValue(e.target.value)}
                className="w-full bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-2.5 focus:outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="block text-xs font-medium text-[var(--text-secondary)]">
                Initial Stage
              </label>
              <select
                value={createStageId}
                onChange={(e) => setCreateStageId(e.target.value)}
                className="w-full bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-2.5 focus:outline-none cursor-pointer"
              >
                {stages.map((st) => (
                  <option key={st.id} value={st.id}>
                    {st.name} ({st.probability ?? 0}%)
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="block text-xs font-medium text-[var(--text-secondary)]">
                Contact / Lead
              </label>
              <select
                value={createLeadId}
                onChange={(e) => setCreateLeadId(e.target.value)}
                className="w-full bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-2.5 focus:outline-none cursor-pointer"
              >
                <option value="">None / Direct</option>
                {leads.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.name} {l.company ? `(${l.company})` : ''}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1">
              <label className="block text-xs font-medium text-[var(--text-secondary)]">
                Assignee
              </label>
              <select
                value={createAssignedTo}
                onChange={(e) => setCreateAssignedTo(e.target.value)}
                className="w-full bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-2.5 focus:outline-none cursor-pointer"
              >
                <option value="">Unassigned</option>
                {teamMembers.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="block text-xs font-medium text-[var(--text-secondary)]">
                Expected Close Date
              </label>
              <input
                type="date"
                value={createExpectedClose}
                onChange={(e) => setCreateExpectedClose(e.target.value)}
                className="w-full bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-2.5 focus:outline-none cursor-pointer"
              />
            </div>

            <div className="space-y-1">
              <label className="block text-xs font-medium text-[var(--text-secondary)]">
                Lead Source
              </label>
              <select
                value={createSource}
                onChange={(e) => setCreateSource(e.target.value)}
                className="w-full bg-[var(--surface-2)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-2.5 focus:outline-none cursor-pointer"
              >
                <option value="inbound">Inbound</option>
                <option value="outbound">Outbound</option>
                <option value="referral">Referral</option>
                <option value="partner">Partner</option>
                <option value="existing_client">Existing Client</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border-subtle)]">
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowCreateModal(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="neutral"
              disabled={createSubmitting}
            >
              Create Deal
            </Button>
          </div>
        </form>
      </Modal>
    </AppShell>
  );
}
