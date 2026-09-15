import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
  X,
  Phone,
  Mail,
  Calendar,
  FileText,
  CheckSquare,
  Upload,
  File as FileIcon,
  User as UserIcon,
  Building,
  ArrowRight,
  TrendingUp,
  Clock,
  Send,
  Download,
} from 'lucide-react';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Input } from '@/Components/UI/Input';
import { Checkbox } from '@/Components/UI/Checkbox';
import { formatINR, formatDateTime, formatRelative } from '@/lib/format';

export interface LeadNote {
  id: number;
  body: string;
  created_at: string;
  creator?: { id: number; name: string } | null;
}

export interface LeadActivity {
  id: number;
  type: 'call' | 'email' | 'meeting' | 'note' | 'task';
  title?: string;
  subject?: string;
  body?: string | null;
  due_at?: string | null;
  created_at: string;
  creator?: { id: number; name: string } | null;
}

export interface LeadFile {
  id: number;
  file_name?: string | null;
  name?: string | null;
  file_path?: string | null;
  path?: string | null;
  file_size?: number | null;
  size?: number | null;
  created_at: string;
  uploader?: { id: number; name: string } | null;
}

export interface LeadDetail {
  id: number;
  name: string;
  company?: string | null;
  email?: string | null;
  phone?: string | null;
  estimated_value: number;
  status: 'open' | 'converted' | 'lost';
  pipeline_id: number;
  stage_id: number;
  assigned_to?: number | null;
  created_at: string;
  pipeline?: { id: number; name: string } | null;
  stage?: { id: number; name: string } | null;
  assigned_user?: { id: number; name: string; email: string } | null;
  notes?: LeadNote[];
  activities?: LeadActivity[];
  files?: LeadFile[];
}

interface LeadPanelProps {
  leadId: number | null;
  isOpen: boolean;
  onClose: () => void;
  onLeadMutated?: () => void;
  pipelines: Array<{ id: number; name: string; stages?: Array<{ id: number; name: string }> }>;
  canManage: boolean;
}

export const LeadPanel: React.FC<LeadPanelProps> = ({
  leadId,
  isOpen,
  onClose,
  onLeadMutated,
  pipelines = [],
  canManage,
}) => {
  const [activeTab, setActiveTab] = useState<'overview' | 'notes' | 'activities' | 'files' | 'convert'>('overview');
  const [lead, setLead] = useState<LeadDetail | null>(null);
  const [teamMembers, setTeamMembers] = useState<Array<{ id: number; name: string; email: string }>>([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Edit lead inline state
  const [isEditingName, setIsEditingName] = useState(false);
  const [leadNameInput, setLeadNameInput] = useState('');

  // Note form state
  const [noteBody, setNoteBody] = useState('');

  // Activity form state
  const [activityType, setActivityType] = useState<'call' | 'email' | 'meeting' | 'task'>('call');
  const [activityTitle, setActivityTitle] = useState('');
  const [activityDueDate, setActivityDueDate] = useState('');

  // File form state
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  // Convert form state
  const [convertName, setConvertName] = useState('');
  const [convertValue, setConvertValue] = useState<number | string>('');
  const [convertCloseDate, setConvertCloseDate] = useState('');
  const [createCustomer, setCreateCustomer] = useState(true);

  const fetchLead = async (id: number) => {
    setLoading(true);
    try {
      const res = await fetch(`/crm/leads/${id}`, {
        headers: { credentials: 'same-origin', Accept: 'application/json' },
      });
      if (res.ok) {
        const data = await res.json();
        setLead(data.lead);
        if (data.teamMembers) setTeamMembers(data.teamMembers);
        setLeadNameInput(data.lead.name || '');
        setConvertName(data.lead.name || '');
        setConvertValue(data.lead.estimated_value || 0);
      }
    } catch (e) {
      console.error('Failed to load lead detail', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen && leadId) {
      fetchLead(leadId);
      setActiveTab('overview');
      setIsEditingName(false);
    } else {
      setLead(null);
    }
  }, [isOpen, leadId]);

  if (!isOpen) return null;

  const currentPipeline = pipelines.find((p) => p.id === lead?.pipeline_id) || pipelines[0];
  const currentStages = currentPipeline?.stages || [];

  const handleStageChange = (newStageId: number) => {
    if (!lead) return;
    setSubmitting(true);
    router.post(
      `/crm/leads/${lead.id}/move`,
      { stage_id: newStageId },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          fetchLead(lead.id);
          onLeadMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleAddNote = (e: React.FormEvent) => {
    e.preventDefault();
    if (!lead || !noteBody.trim()) return;
    setSubmitting(true);
    router.post(
      `/crm/lead/${lead.id}/notes`,
      { body: noteBody },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          setNoteBody('');
          fetchLead(lead.id);
          onLeadMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleAddActivity = (e: React.FormEvent) => {
    e.preventDefault();
    if (!lead || !activityTitle.trim()) return;
    setSubmitting(true);
    router.post(
      `/crm/lead/${lead.id}/activities`,
      {
        type: activityType,
        title: activityTitle,
        due_at: activityDueDate || null,
        assigned_to: lead.assigned_to || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          setActivityTitle('');
          setActivityDueDate('');
          fetchLead(lead.id);
          onLeadMutated?.();
        },
        onError: () => setSubmitting(false),
      }
    );
  };

  const handleUploadFile = (e: React.FormEvent) => {
    e.preventDefault();
    if (!lead || !selectedFile) return;
    setSubmitting(true);
    const formData = new FormData();
    formData.append('file', selectedFile);

    router.post(`/crm/lead/${lead.id}/files`, formData, {
      preserveScroll: true,
      onSuccess: () => {
        setSubmitting(false);
        setSelectedFile(null);
        fetchLead(lead.id);
        onLeadMutated?.();
      },
      onError: () => setSubmitting(false),
    });
  };

  const handleConvert = (e: React.FormEvent) => {
    e.preventDefault();
    if (!lead) return;
    setSubmitting(true);
    router.post(
      `/crm/leads/${lead.id}/convert`,
      {
        name: convertName || lead.name,
        value: Number(convertValue) || 0,
        expected_close_on: convertCloseDate || null,
        create_customer: createCustomer,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          onClose();
          onLeadMutated?.();
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
        {/* Header */}
        <div className="flex items-start justify-between p-5 border-b border-[var(--border-subtle)] bg-[var(--surface-2)]">
          <div className="space-y-1 max-w-[80%]">
            <div className="flex items-center gap-2">
              <h2 className="text-base font-semibold text-[var(--text-primary)] truncate">
                {lead?.name ?? 'Loading Lead...'}
              </h2>
              {lead?.status === 'converted' && (
                <Badge variant="success" size="sm">Converted</Badge>
              )}
              {lead?.status === 'lost' && (
                <Badge variant="danger" size="sm">Lost</Badge>
              )}
              {lead?.status === 'open' && (
                <Badge variant="neutral" size="sm">Open</Badge>
              )}
            </div>
            <div className="flex items-center gap-3 text-xs text-[var(--text-secondary)]">
              <span className="font-semibold text-[var(--text-primary)] text-sm">
                {lead ? formatINR(lead.estimated_value) : '—'}
              </span>
              <span>•</span>
              <span className="truncate flex items-center gap-1">
                <Building className="h-3 w-3" />
                {lead?.company || 'Direct Prospect'}
              </span>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface-3)] transition-colors cursor-pointer"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Navigation Tabs */}
        <div className="flex border-b border-[var(--border-subtle)] bg-[var(--surface-1)] px-5 gap-6 text-xs font-medium overflow-x-auto scrollbar-none">
          {(['overview', 'notes', 'activities', 'files', 'convert'] as const).map((tab) => {
            if (tab === 'convert' && lead?.status !== 'open') return null;
            return (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`py-3 capitalize border-b-2 cursor-pointer transition-colors whitespace-nowrap ${
                  activeTab === tab
                    ? 'border-[var(--brand-primary)] text-[var(--text-primary)] font-semibold'
                    : 'border-transparent text-[var(--text-tertiary)] hover:text-[var(--text-secondary)]'
                }`}
              >
                {tab}
                {tab === 'notes' && lead?.notes && lead.notes.length > 0 && (
                  <span className="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--surface-3)] text-[var(--text-secondary)]">
                    {lead.notes.length}
                  </span>
                )}
                {tab === 'activities' && lead?.activities && lead.activities.length > 0 && (
                  <span className="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--surface-3)] text-[var(--text-secondary)]">
                    {lead.activities.length}
                  </span>
                )}
                {tab === 'files' && lead?.files && lead.files.length > 0 && (
                  <span className="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--surface-3)] text-[var(--text-secondary)]">
                    {lead.files.length}
                  </span>
                )}
              </button>
            );
          })}
        </div>

        {/* Tab Content Body */}
        <div className="flex-1 overflow-y-auto p-5 space-y-6">
          {loading && (
            <div className="flex flex-col items-center justify-center h-48 text-[var(--text-tertiary)] space-y-2">
              <div className="h-6 w-6 border-2 border-[var(--brand-primary)] border-t-transparent rounded-full animate-spin" />
              <p className="text-xs">Loading lead details...</p>
            </div>
          )}

          {/* OVERVIEW TAB */}
          {!loading && lead && activeTab === 'overview' && (
            <div className="space-y-6">
              {/* Pipeline Stage Bar */}
              {currentStages.length > 0 && (
                <div className="space-y-2">
                  <span className="text-xs font-semibold text-[var(--text-secondary)] uppercase tracking-wider">
                    Pipeline Stage
                  </span>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5 p-1.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)]">
                    {currentStages.map((st) => (
                      <button
                        key={st.id}
                        disabled={submitting || !canManage}
                        onClick={() => handleStageChange(st.id)}
                        className={`text-xs py-1.5 px-2 rounded-lg font-medium transition-all text-center truncate cursor-pointer ${
                          lead.stage_id === st.id
                            ? 'bg-[var(--brand-primary)] text-white shadow-sm'
                            : 'text-[var(--text-secondary)] hover:bg-[var(--surface-3)] hover:text-[var(--text-primary)]'
                        }`}
                      >
                        {st.name}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Lead Details Grid */}
              <div className="grid grid-cols-2 gap-3 text-xs">
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Lead Name</span>
                  <p className="font-semibold text-[var(--text-primary)] truncate">{lead.name}</p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Company</span>
                  <p className="font-medium text-[var(--text-primary)] truncate">{lead.company || '—'}</p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Email Address</span>
                  {lead.email ? (
                    <a href={`mailto:${lead.email}`} className="font-medium text-[var(--brand-primary)] hover:underline truncate block">
                      {lead.email}
                    </a>
                  ) : (
                    <p className="text-[var(--text-tertiary)]">—</p>
                  )}
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Phone Number</span>
                  {lead.phone ? (
                    <a href={`tel:${lead.phone}`} className="font-medium text-[var(--brand-primary)] hover:underline truncate block">
                      {lead.phone}
                    </a>
                  ) : (
                    <p className="text-[var(--text-tertiary)]">—</p>
                  )}
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Estimated Value</span>
                  <p className="font-semibold text-emerald-400 tabular-nums">
                    {formatINR(lead.estimated_value)}
                  </p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Assigned Owner</span>
                  <p className="font-medium text-[var(--text-primary)] flex items-center gap-1.5 truncate">
                    <UserIcon className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />
                    {lead.assigned_user?.name || 'Unassigned'}
                  </p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Pipeline</span>
                  <p className="font-medium text-[var(--text-primary)] truncate">
                    {lead.pipeline?.name || currentPipeline?.name || 'Default Sales Pipeline'}
                  </p>
                </div>
                <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1">
                  <span className="text-[var(--text-tertiary)]">Created Date</span>
                  <p className="font-medium text-[var(--text-primary)] truncate">
                    {formatDateTime(lead.created_at)}
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* NOTES TAB */}
          {!loading && lead && activeTab === 'notes' && (
            <div className="space-y-5">
              <form onSubmit={handleAddNote} className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-3">
                <span className="text-xs font-semibold text-[var(--text-primary)]">Add Note</span>
                <textarea
                  placeholder="Record an observation, discovery meeting note, or customer detail..."
                  value={noteBody}
                  onChange={(e) => setNoteBody(e.target.value)}
                  required
                  rows={3}
                  className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-lg border border-[var(--border-medium)] p-2.5 placeholder:text-[var(--text-tertiary)] focus:outline-none"
                />
                <div className="flex justify-end">
                  <Button type="submit" variant="neutral" size="sm" loading={submitting} icon={<Send className="w-3.5 h-3.5" />}>
                    Save Note
                  </Button>
                </div>
              </form>

              <div className="space-y-3">
                {(!lead.notes || lead.notes.length === 0) ? (
                  <div className="p-8 text-center text-xs text-[var(--text-tertiary)] rounded-xl border border-dashed border-[var(--border-subtle)]">
                    No notes yet. Add the first one.
                  </div>
                ) : (
                  lead.notes.map((n) => (
                    <div key={n.id} className="p-3.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-1.5">
                      <div className="flex items-center justify-between text-[11px] text-[var(--text-tertiary)]">
                        <span className="font-medium text-[var(--text-secondary)]">{n.creator?.name || 'System User'}</span>
                        <span>{formatRelative(n.created_at)}</span>
                      </div>
                      <p className="text-xs text-[var(--text-primary)] whitespace-pre-wrap">{n.body}</p>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}

          {/* ACTIVITIES TAB */}
          {!loading && lead && activeTab === 'activities' && (
            <div className="space-y-5">
              <form onSubmit={handleAddActivity} className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-3">
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
                  placeholder="Subject (e.g. Schedule discovery demo)"
                  value={activityTitle}
                  onChange={(e) => setActivityTitle(e.target.value)}
                  required
                  className="w-full bg-[var(--surface-3)] text-xs text-[var(--text-primary)] rounded-lg border border-[var(--border-medium)] px-2.5 py-1.5 placeholder:text-[var(--text-tertiary)]"
                />
                <div className="flex justify-end">
                  <Button type="submit" variant="neutral" size="sm" loading={submitting}>
                    Add Activity
                  </Button>
                </div>
              </form>

              <div className="space-y-3">
                {(!lead.activities || lead.activities.length === 0) ? (
                  <div className="p-8 text-center text-xs text-[var(--text-tertiary)] rounded-xl border border-dashed border-[var(--border-subtle)]">
                    No activities recorded yet.
                  </div>
                ) : (
                  lead.activities.map((act) => (
                    <div key={act.id} className="p-3.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] flex items-start gap-3">
                      <div className="p-2 rounded-lg bg-[var(--surface-3)] shrink-0 mt-0.5">
                        {getActivityIcon(act.type)}
                      </div>
                      <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-semibold text-[var(--text-primary)] truncate">
                            {act.title || act.subject || 'Activity'}
                          </span>
                          <span className="text-[10px] text-[var(--text-tertiary)]">
                            {formatRelative(act.created_at)}
                          </span>
                        </div>
                        {act.due_at && (
                          <p className="text-[11px] text-amber-400 flex items-center gap-1">
                            <Clock className="w-3 h-3" /> Due: {formatDateTime(act.due_at)}
                          </p>
                        )}
                        <p className="text-[11px] text-[var(--text-tertiary)]">Logged by {act.creator?.name || 'User'}</p>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}

          {/* FILES TAB */}
          {!loading && lead && activeTab === 'files' && (
            <div className="space-y-5">
              <form onSubmit={handleUploadFile} className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-3">
                <span className="text-xs font-semibold text-[var(--text-primary)]">Upload Document</span>
                <input
                  type="file"
                  onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                  className="block w-full text-xs text-[var(--text-secondary)] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[var(--surface-3)] file:text-[var(--text-primary)] cursor-pointer"
                />
                <div className="flex justify-end">
                  <Button type="submit" variant="neutral" size="sm" loading={submitting} disabled={!selectedFile} icon={<Upload className="w-3.5 h-3.5" />}>
                    Upload File
                  </Button>
                </div>
              </form>

              <div className="space-y-3">
                {(!lead.files || lead.files.length === 0) ? (
                  <div className="p-8 text-center text-xs text-[var(--text-tertiary)] rounded-xl border border-dashed border-[var(--border-subtle)]">
                    No files uploaded yet.
                  </div>
                ) : (
                  lead.files.map((file) => {
                    const filePath = file.path || file.file_path;
                    const fileName = file.name || file.file_name || 'Document';
                    return (
                      <div key={file.id} className="p-3.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5 min-w-0">
                          <FileIcon className="w-4 h-4 text-[var(--text-tertiary)] shrink-0" />
                          <div className="min-w-0">
                            <p className="text-xs font-medium text-[var(--text-primary)] truncate">{fileName}</p>
                            <p className="text-[10px] text-[var(--text-tertiary)]">
                              {file.uploader?.name ? `Uploaded by ${file.uploader.name} · ` : ''}{formatRelative(file.created_at)}
                            </p>
                          </div>
                        </div>
                        {filePath && (
                          <a
                            href={filePath.startsWith('http') || filePath.startsWith('/') ? filePath : `/storage/${filePath}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface-3)]"
                          >
                            <Download className="w-4 h-4" />
                          </a>
                        )}
                      </div>
                    );
                  })
                )}
              </div>
            </div>
          )}

          {/* CONVERT TAB */}
          {!loading && lead && activeTab === 'convert' && lead.status === 'open' && (
            <div className="space-y-4">
              <div className="p-4 rounded-xl bg-[var(--brand-primary)]/10 border border-[var(--brand-primary)]/20 space-y-1">
                <span className="text-xs font-semibold text-[var(--text-primary)] flex items-center gap-1.5">
                  <TrendingUp className="w-4 h-4 text-[var(--brand-primary)]" />
                  Convert Lead to Active Deal
                </span>
                <p className="text-[11px] text-[var(--text-secondary)]">
                  Converting this lead will mark it as converted and spawn an active deal inside your sales pipeline.
                </p>
              </div>

              <form onSubmit={handleConvert} className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-4">
                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Deal Title *</label>
                  <Input
                    value={convertName}
                    onChange={(e) => setConvertName(e.target.value)}
                    placeholder="e.g. Enterprise Solution Contract"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Estimated Value (₹)</label>
                  <Input
                    type="number"
                    value={convertValue}
                    onChange={(e) => setConvertValue(e.target.value)}
                    placeholder="0"
                    min="0"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Expected Close Date</label>
                  <Input
                    type="date"
                    value={convertCloseDate}
                    onChange={(e) => setConvertCloseDate(e.target.value)}
                  />
                </div>

                <div className="pt-1">
                  <Checkbox
                    id="create_customer_checkbox"
                    checked={createCustomer}
                    onChange={(e) => setCreateCustomer(e.target.checked)}
                    label="Also create a customer account"
                    description="Automatically creates a record in Accounting Customers using the lead details."
                  />
                </div>

                <div className="pt-2">
                  <Button
                    type="submit"
                    variant="neutral"
                    loading={submitting}
                    icon={<ArrowRight className="w-4 h-4" />}
                    className="w-full"
                  >
                    Convert to Deal
                  </Button>
                </div>
              </form>
            </div>
          )}
        </div>
      </div>
    </>
  );
};
