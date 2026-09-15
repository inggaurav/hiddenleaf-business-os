import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
  X,
  Trophy,
  XCircle,
  Phone,
  Mail,
  Calendar,
  FileText,
  CheckSquare,
  Upload,
  File as FileIcon,
  ShieldAlert,
  Percent,
  User as UserIcon,
  Building,
} from 'lucide-react';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { formatINR, formatDateTime, formatRelative } from '@/lib/format';

export interface DealActivity {
  id: number;
  deal_id: number;
  type: 'call' | 'email' | 'meeting' | 'note' | 'task';
  subject: string;
  body?: string | null;
  description?: string | null;
  scheduled_at?: string | null;
  due_date?: string | null;
  completed_at?: string | null;
  created_at: string;
  user?: { id: number; name: string } | null;
}

export interface DealFile {
  id: number;
  deal_id: number;
  file_name?: string | null;
  name?: string | null;
  file_path?: string | null;
  path?: string | null;
  file_size?: number | null;
  size?: number | null;
  created_at: string;
  user?: { id: number; name: string } | null;
}

export interface DealApproval {
  id: number;
  deal_id: number;
  discount_percentage?: number | null;
  status: 'pending' | 'approved' | 'rejected';
  approval_status?: string | null;
  notes?: string | null;
  decided_at?: string | null;
  created_at: string;
  requester?: { id: number; name: string } | null;
  approver?: { id: number; name: string } | null;
}

export interface DealDetail {
  id: number;
  name: string;
  value: number;
  pipeline_id: number;
  stage_id: number;
  position: number;
  probability: number;
  status: string;
  source?: string | null;
  expected_close_date?: string | null;
  actual_close_date?: string | null;
  lost_reason?: string | null;
  created_at: string;
  customer?: string;
  assigned_user?: { id: number; name: string; email: string } | null;
  assigned_to?: number | null;
  lead?: { id: number; name: string; company?: string; phone?: string; email?: string } | null;
  pipeline?: { id: number; name: string } | null;
  stage?: { id: number; name: string; probability?: number; outcome?: string | null } | null;
  activities?: DealActivity[];
  files?: DealFile[];
  approvals?: DealApproval[];
}

interface DealPanelProps {
  dealId: number | null;
  isOpen: boolean;
  onClose: () => void;
  onDealMutated?: () => void;
  pipelines: Array<{ id: number; name: string }>;
  stages: Array<{ id: number; name: string; probability?: number }>;
  teamMembers: Array<{ id: number; name: string; email: string }>;
  canManage: boolean;
}

export const DealPanel: React.FC<DealPanelProps> = ({
  dealId,
  isOpen,
  onClose,
  onDealMutated,
  pipelines,
  stages,
  teamMembers,
  canManage,
}) => {
  const [activeTab, setActiveTab] = useState<'overview' | 'activities' | 'files' | 'approvals'>('overview');
  const [deal, setDeal] = useState<DealDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [showLostModal, setShowLostModal] = useState(false);
  const [lostReasonInput, setLostReasonInput] = useState('');

  const [activityType, setActivityType] = useState<'call' | 'email' | 'meeting' | 'note' | 'task'>('call');
  const [activitySubject, setActivitySubject] = useState('');
  const [activityBody, setActivityBody] = useState('');
  const [activityDueDate, setActivityDueDate] = useState('');

  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [discountInput, setDiscountInput] = useState('');

  const fetchDeal = async (id: number) => {
    setLoading(true);
    try {
      const res = await fetch(`/crm/deals/${id}`, {
        headers: { Accept: 'application/json' },
      });
      if (res.ok) {
        const data = await res.json();
        setDeal(data.deal);
      }
    } catch (e) {
      console.error('Failed to load deal detail', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen && dealId) {
      fetchDeal(dealId);
      setActiveTab('overview');
    } else {
      setDeal(null);
    }
  }, [isOpen, dealId]);

  if (!isOpen) return null;

  const handleMarkWon = () => {
    if (!deal) return;
    setSubmitting(true);
    router.post(
      `/crm/deals/${deal.id}/won`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          fetchDeal(deal.id);
          onDealMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleMarkLost = () => {
    if (!deal || !lostReasonInput.trim()) return;
    setSubmitting(true);
    router.post(
      `/crm/deals/${deal.id}/lost`,
      { lost_reason: lostReasonInput },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          setShowLostModal(false);
          setLostReasonInput('');
          fetchDeal(deal.id);
          onDealMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleStageChange = (newStageId: number) => {
    if (!deal) return;
    setSubmitting(true);
    router.post(
      `/crm/deals/${deal.id}/move`,
      { stage_id: newStageId, pipeline_id: deal.pipeline_id },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          fetchDeal(deal.id);
          onDealMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleLogActivity = (e: React.FormEvent) => {
    e.preventDefault();
    if (!deal || !activitySubject.trim()) return;
    setSubmitting(true);
    router.post(
      `/crm/deals/${deal.id}/activities`,
      {
        type: activityType,
        subject: activitySubject,
        body: activityBody,
        scheduled_at: activityDueDate || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          setActivitySubject('');
          setActivityBody('');
          setActivityDueDate('');
          fetchDeal(deal.id);
          onDealMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleUploadFile = (e: React.FormEvent) => {
    e.preventDefault();
    if (!deal || !selectedFile) return;
    setSubmitting(true);
    const formData = new FormData();
    formData.append('file', selectedFile);

    router.post(`/crm/deals/${deal.id}/files`, formData, {
      preserveScroll: true,
      onSuccess: () => {
        setSubmitting(false);
        setSelectedFile(null);
        fetchDeal(deal.id);
        onDealMutated?.();
      },
      onError: () => setSubmitting(false),
    });
  };

  const handleRequestApproval = (e: React.FormEvent) => {
    e.preventDefault();
    if (!deal || !discountInput) return;
    setSubmitting(true);
    router.post(
      `/crm/deals/${deal.id}/approvals`,
      { discount_percentage: parseFloat(discountInput) },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          setDiscountInput('');
          fetchDeal(deal.id);
          onDealMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleDecideApproval = (approvalId: number, decision: 'approved' | 'rejected') => {
    setSubmitting(true);
    router.post(
      `/crm/deals/approvals/${approvalId}/decision`,
      { decision, notes: 'Decided via Deals Kanban board' },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          if (deal) fetchDeal(deal.id);
          onDealMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'call':
        return <Phone className="h-4 w-4 text-emerald-400" />;
      case 'email':
        return <Mail className="h-4 w-4 text-sky-400" />;
      case 'meeting':
        return <Calendar className="h-4 w-4 text-amber-400" />;
      case 'task':
        return <CheckSquare className="h-4 w-4 text-purple-400" />;
      default:
        return <FileText className="h-4 w-4 text-gray-400" />;
    }
  };

  return (
    <>
      <div
        className="fixed inset-0 bg-black/60 backdrop-blur-xs z-40 transition-opacity"
        onClick={onClose}
      />

      <div className="fixed inset-y-0 right-0 z-50 w-full sm:w-[520px] max-w-full bg-[var(--surface-1)] border-l border-[var(--border-subtle)] shadow-2xl flex flex-col animate-in slide-in-from-right duration-200">
        <div className="flex items-start justify-between p-5 border-b border-[var(--border-subtle)] bg-[var(--surface-2)]">
          <div className="space-y-1 max-w-[80%]">
            <div className="flex items-center gap-2">
              <h2 className="text-base font-semibold text-[var(--text-primary)] truncate">
                {deal?.name ?? 'Loading Deal...'}
              </h2>
              {deal?.status === 'won' && (
                <Badge variant="success" size="sm">Won</Badge>
              )}
              {deal?.status === 'lost' && (
                <Badge variant="danger" size="sm">Lost</Badge>
              )}
            </div>
            <div className="flex items-center gap-3 text-xs text-[var(--text-secondary)]">
              <span className="font-semibold text-[var(--text-primary)] text-sm">
                {deal ? formatINR(deal.value) : '—'}
              </span>
              <span>•</span>
              <span>{deal?.probability ?? 0}% probability</span>
              {deal?.customer && (
                <>
                  <span>•</span>
                  <span className="truncate flex items-center gap-1">
                    <Building className="h-3 w-3" />
                    {deal.customer}
                  </span>
                </>
              )}
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface-3)] transition-colors cursor-pointer"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="flex border-b border-[var(--border-subtle)] bg-[var(--surface-1)] px-5 gap-6 text-xs font-medium">
          {(['overview', 'activities', 'files', 'approvals'] as const).map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`py-3 capitalize border-b-2 cursor-pointer transition-colors ${
                activeTab === tab
                  ? 'border-[var(--brand-primary)] text-[var(--text-primary)] font-semibold'
                  : 'border-transparent text-[var(--text-tertiary)] hover:text-[var(--text-secondary)]'
              }`}
            >
              {tab}
              {tab === 'activities' && deal?.activities && deal.activities.length > 0 && (
                <span className="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--surface-3)] text-[var(--text-secondary)]">
                  {deal.activities.length}
                </span>
              )}
              {tab === 'files' && deal?.files && deal.files.length > 0 && (
                <span className="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--surface-3)] text-[var(--text-secondary)]">
                  {deal.files.length}
                </span>
              )}
              {tab === 'approvals' && deal?.approvals && deal.approvals.length > 0 && (
                <span className="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--surface-3)] text-[var(--text-secondary)]">
                  {deal.approvals.length}
                </span>
              )}
            </button>
          ))}
        </div>

        <div className="flex-1 overflow-y-auto p-5 space-y-6">
          {loading && (
            <div className="py-12 text-center text-xs text-[var(--text-tertiary)]">
              Loading deal information...
            </div>
          )}

          {!loading && deal && activeTab === 'overview' && (
            <div className="space-y-6">
              <div className="flex items-center gap-2 p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)]">
                <span className="text-xs text-[var(--text-secondary)] mr-auto">Stage Action:</span>
                {deal.status !== 'won' && (
                  <Button
                    size="sm"
                    variant="neutral"
                    icon={<Trophy className="h-3.5 w-3.5 text-amber-400" />}
                    onClick={handleMarkWon}
                    disabled={submitting}
                  >
                    Mark Won
                  </Button>
                )}
                {deal.status !== 'lost' && (
                  <Button
                    size="sm"
                    variant="danger"
                    icon={<XCircle className="h-3.5 w-3.5" />}
                    onClick={() => setShowLostModal(true)}
                    disabled={submitting}
                  >
                    Mark Lost
                  </Button>
                )}
              </div>

              <div className="space-y-2">
                <label className="block text-xs font-medium text-[var(--text-secondary)]">
                  Current Pipeline Stage
                </label>
                <select
                  value={deal.stage_id}
                  onChange={(e) => handleStageChange(Number(e.target.value))}
                  disabled={submitting}
                  className="w-full bg-[var(--surface-2)] text-[var(--text-primary)] text-sm rounded-xl border border-[var(--border-medium)] px-3.5 py-2.5 focus:outline-none cursor-pointer"
                >
                  {stages.map((st) => (
                    <option key={st.id} value={st.id}>
                      {st.name} ({st.probability ?? 0}%)
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4 text-xs">
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Source</span>
                  <p className="font-medium text-[var(--text-primary)] capitalize">
                    {deal.source || 'Direct / Organic'}
                  </p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Expected Close</span>
                  <p className="font-medium text-[var(--text-primary)]">
                    {deal.expected_close_date || 'Not set'}
                  </p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Assigned Owner</span>
                  <p className="font-medium text-[var(--text-primary)] flex items-center gap-1.5">
                    <UserIcon className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />
                    {deal.assigned_user?.name || 'Unassigned'}
                  </p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Pipeline</span>
                  <p className="font-medium text-[var(--text-primary)]">
                    {deal.pipeline?.name || 'Default Pipeline'}
                  </p>
                </div>
              </div>

              {deal.lead && (
                <div className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-2">
                  <span className="text-xs font-semibold text-[var(--text-primary)] flex items-center gap-1.5">
                    <Building className="h-4 w-4 text-[var(--text-secondary)]" />
                    Associated Contact
                  </span>
                  <div className="text-xs space-y-1 text-[var(--text-secondary)]">
                    <p className="font-medium text-[var(--text-primary)]">{deal.lead.name}</p>
                    {deal.lead.company && <p>Company: {deal.lead.company}</p>}
                    {deal.lead.email && <p>Email: {deal.lead.email}</p>}
                    {deal.lead.phone && <p>Phone: {deal.lead.phone}</p>}
                  </div>
                </div>
              )}

              {deal.status === 'lost' && (
                <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs space-y-1">
                  <span className="font-semibold text-rose-400">Lost Reason</span>
                  <p className="text-[var(--text-primary)]">{deal.lost_reason || 'No reason provided'}</p>
                </div>
              )}
            </div>
          )}

          {!loading && deal && activeTab === 'activities' && (
            <div className="space-y-6">
              <form onSubmit={handleLogActivity} className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-3">
                <span className="text-xs font-semibold text-[var(--text-primary)]">Log New Activity</span>
                <div className="grid grid-cols-2 gap-2">
                  <select
                    value={activityType}
                    onChange={(e: any) => setActivityType(e.target.value)}
                    className="bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-lg border border-[var(--border-medium)] px-2.5 py-1.5 cursor-pointer"
                  >
                    <option value="call">Call</option>
                    <option value="meeting">Meeting</option>
                    <option value="email">Email</option>
                    <option value="note">Note</option>
                    <option value="task">Task</option>
                  </select>
                  <input
                    type="datetime-local"
                    value={activityDueDate}
                    onChange={(e) => setActivityDueDate(e.target.value)}
                    className="bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-lg border border-[var(--border-medium)] px-2.5 py-1.5"
                  />
                </div>
                <input
                  type="text"
                  placeholder="Subject (e.g. Follow-up demo call)"
                  value={activitySubject}
                  onChange={(e) => setActivitySubject(e.target.value)}
                  required
                  className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-lg border border-[var(--border-medium)] px-2.5 py-1.5 placeholder:text-[var(--text-tertiary)]"
                />
                <textarea
                  placeholder="Notes / description..."
                  value={activityBody}
                  onChange={(e) => setActivityBody(e.target.value)}
                  rows={2}
                  className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-lg border border-[var(--border-medium)] px-2.5 py-1.5 placeholder:text-[var(--text-tertiary)] resize-none"
                />
                <div className="flex justify-end">
                  <Button size="sm" variant="neutral" type="submit" disabled={submitting}>
                    Log Activity
                  </Button>
                </div>
              </form>

              <div className="space-y-3">
                <span className="text-xs font-medium text-[var(--text-tertiary)] uppercase tracking-wider">
                  Timeline
                </span>
                {(!deal.activities || deal.activities.length === 0) ? (
                  <p className="text-xs text-[var(--text-tertiary)] py-4 text-center">
                    No activities recorded yet.
                  </p>
                ) : (
                  <div className="space-y-2">
                    {deal.activities.map((act) => (
                      <div
                        key={act.id}
                        className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1.5"
                      >
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            {getActivityIcon(act.type)}
                            <span className="text-xs font-semibold text-[var(--text-primary)]">
                              {act.subject}
                            </span>
                          </div>
                          <span className="text-[10px] text-[var(--text-tertiary)]">
                            {formatRelative(act.created_at)}
                          </span>
                        </div>
                        {(act.body || act.description) && (
                          <p className="text-xs text-[var(--text-secondary)] pl-6">
                            {act.body || act.description}
                          </p>
                        )}
                        <div className="flex items-center justify-between pl-6 text-[10px] text-[var(--text-tertiary)]">
                          <span>By {act.user?.name || 'User'}</span>
                          {(act.due_date || act.scheduled_at) && (
                            <span className="text-amber-400">
                              Due: {formatRelative(act.due_date || act.scheduled_at)}
                            </span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}

          {!loading && deal && activeTab === 'files' && (
            <div className="space-y-6">
              <form onSubmit={handleUploadFile} className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-3">
                <span className="text-xs font-semibold text-[var(--text-primary)]">Upload Attachment</span>
                <input
                  type="file"
                  onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                  className="block w-full text-xs text-[var(--text-secondary)] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[var(--surface-3)] file:text-[var(--text-primary)] hover:file:bg-[var(--border-medium)] cursor-pointer"
                />
                <div className="flex justify-end">
                  <Button
                    size="sm"
                    variant="neutral"
                    type="submit"
                    disabled={!selectedFile || submitting}
                    icon={<Upload className="h-3.5 w-3.5" />}
                  >
                    Upload File
                  </Button>
                </div>
              </form>

              <div className="space-y-2">
                <span className="text-xs font-medium text-[var(--text-tertiary)] uppercase tracking-wider">
                  Attached Files
                </span>
                {(!deal.files || deal.files.length === 0) ? (
                  <p className="text-xs text-[var(--text-tertiary)] py-4 text-center">
                    No files attached.
                  </p>
                ) : (
                  deal.files.map((f) => (
                    <div
                      key={f.id}
                      className="flex items-center justify-between p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)]"
                    >
                      <div className="flex items-center gap-2.5 truncate max-w-[80%]">
                        <FileIcon className="h-4 w-4 text-[var(--text-secondary)] shrink-0" />
                        <div className="truncate">
                          <p className="text-xs font-medium text-[var(--text-primary)] truncate">
                            {f.file_name || f.name || 'Attachment'}
                          </p>
                          <p className="text-[10px] text-[var(--text-tertiary)]">
                            {Math.round(((f.file_size || f.size || 0) / 1024))} KB • By {f.user?.name || 'User'}
                          </p>
                        </div>
                      </div>
                      <a
                        href={`/storage/${f.file_path || f.path}`}
                        target="_blank"
                        rel="noreferrer"
                        className="text-xs text-[var(--brand-primary)] hover:underline"
                      >
                        View
                      </a>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}

          {!loading && deal && activeTab === 'approvals' && (
            <div className="space-y-6">
              <form onSubmit={handleRequestApproval} className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-3">
                <div className="flex items-center gap-2">
                  <ShieldAlert className="h-4 w-4 text-amber-400" />
                  <span className="text-xs font-semibold text-[var(--text-primary)]">
                    Request Discount Approval
                  </span>
                </div>
                <p className="text-xs text-[var(--text-secondary)]">
                  Deals requiring discounts higher than default policy require manager authorization.
                </p>
                <div className="flex items-center gap-2">
                  <div className="relative flex-1">
                    <input
                      type="number"
                      step="0.1"
                      min="1"
                      max="100"
                      placeholder="e.g. 25"
                      value={discountInput}
                      onChange={(e) => setDiscountInput(e.target.value)}
                      required
                      className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-lg border border-[var(--border-medium)] px-3 py-2"
                    />
                    <Percent className="absolute right-3 top-2.5 h-3.5 w-3.5 text-[var(--text-tertiary)]" />
                  </div>
                  <Button size="sm" variant="neutral" type="submit" disabled={submitting}>
                    Request
                  </Button>
                </div>
              </form>

              <div className="space-y-2">
                <span className="text-xs font-medium text-[var(--text-tertiary)] uppercase tracking-wider">
                  Approval Requests
                </span>
                {(!deal.approvals || deal.approvals.length === 0) ? (
                  <p className="text-xs text-[var(--text-tertiary)] py-4 text-center">
                    No discount approvals requested for this deal.
                  </p>
                ) : (
                  deal.approvals.map((app) => (
                    <div
                      key={app.id}
                      className="p-3.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-2"
                    >
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-[var(--text-primary)]">
                          {app.discount_percentage ? `${app.discount_percentage}% Discount` : 'Approval'}
                        </span>
                        <Badge
                          variant={
                            app.status === 'approved'
                              ? 'success'
                              : app.status === 'rejected'
                              ? 'danger'
                              : 'warning'
                          }
                          size="sm"
                        >
                          {app.status}
                        </Badge>
                      </div>

                      <div className="text-[10px] text-[var(--text-tertiary)] space-y-0.5">
                        <p>Requested by: {app.requester?.name || 'User'}</p>
                        {app.decided_at && (
                          <p>
                            Decided by: {app.approver?.name || 'Manager'} • {formatDateTime(app.decided_at)}
                          </p>
                        )}
                        {app.notes && <p className="text-[var(--text-secondary)] italic">Notes: {app.notes}</p>}
                      </div>

                      {app.status === 'pending' && canManage && (
                        <div className="flex items-center justify-end gap-2 pt-1 border-t border-[var(--border-subtle)]">
                          <Button
                            size="sm"
                            variant="danger"
                            onClick={() => handleDecideApproval(app.id, 'rejected')}
                            disabled={submitting}
                          >
                            Reject
                          </Button>
                          <Button
                            size="sm"
                            variant="neutral"
                            onClick={() => handleDecideApproval(app.id, 'approved')}
                            disabled={submitting}
                          >
                            Approve
                          </Button>
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {showLostModal && (
        <div className="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
          <div className="w-full max-w-md p-5 rounded-2xl bg-[var(--surface-2)] border border-[var(--border-medium)] shadow-2xl space-y-4">
            <h3 className="text-sm font-semibold text-[var(--text-primary)]">
              Mark Deal as Lost
            </h3>
            <p className="text-xs text-[var(--text-secondary)]">
              Please specify the reason for losing this deal. This information will be saved for pipeline analytics.
            </p>
            <textarea
              rows={3}
              placeholder="e.g. Budget constraints, chosen competitor, project canceled..."
              value={lostReasonInput}
              onChange={(e) => setLostReasonInput(e.target.value)}
              className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-xl border border-[var(--border-medium)] p-3 focus:outline-none placeholder:text-[var(--text-tertiary)] resize-none"
            />
            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setShowLostModal(false);
                  setLostReasonInput('');
                }}
              >
                Cancel
              </Button>
              <Button
                variant="danger"
                size="sm"
                onClick={handleMarkLost}
                disabled={!lostReasonInput.trim() || submitting}
              >
                Confirm Lost
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};
