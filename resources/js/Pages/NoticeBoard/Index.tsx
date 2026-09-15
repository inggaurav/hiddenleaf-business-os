import React, { useState, useEffect } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
  Plus,
  Search,
  Pin,
  Eye,
  MessageSquare,
  Edit2,
  Trash2,
  CheckCircle,
  XCircle,
  Layers,
  ArrowLeft,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Modal } from '@/Components/UI/Modal';
import { DataTable, Column, LaravelPaginator } from '@/Components/UI/DataTable';
import { formatDate } from '@/lib/format';
import { NoticeItem } from './types';

interface IndexProps {
  notices: LaravelPaginator<NoticeItem>;
  canManage: boolean;
  filters: {
    search?: string;
    priority?: string;
    status?: string;
    target_type?: string;
  };
}

export default function Index({ notices, canManage, filters }: IndexProps) {
  const [modalOpen, setModalOpen] = useState(false);
  const [editingNotice, setEditingNotice] = useState<NoticeItem | null>(null);
  const [targetOptions, setTargetOptions] = useState<Array<{ id: number; name: string }>>([]);
  const [loadingOptions, setLoadingOptions] = useState(false);

  const form = useForm({
    title: '',
    description: '',
    start_date: new Date().toISOString().split('T')[0],
    expiry_date: '',
    priority: 'normal',
    target_type: 'all',
    target_ids: [] as number[],
    is_pinned: false,
    require_acknowledgment: false,
    allow_comments: true,
    status: 'published',
  });

  const openCreateModal = () => {
    setEditingNotice(null);
    form.reset();
    form.setData({
      title: '',
      description: '',
      start_date: new Date().toISOString().split('T')[0],
      expiry_date: '',
      priority: 'normal',
      target_type: 'all',
      target_ids: [],
      is_pinned: false,
      require_acknowledgment: false,
      allow_comments: true,
      status: 'published',
    });
    setModalOpen(true);
  };

  const openEditModal = (notice: NoticeItem) => {
    setEditingNotice(notice);
    const targetIds = notice.targets?.map((t) => {
      if (notice.target_type === 'department') return t.department_id;
      if (notice.target_type === 'role') return t.role_id;
      return t.user_id;
    }).filter(Boolean) as number[] || [];

    form.setData({
      title: notice.title,
      description: notice.description || '',
      start_date: notice.start_date ? notice.start_date.split('T')[0] : '',
      expiry_date: notice.expiry_date ? notice.expiry_date.split('T')[0] : '',
      priority: notice.priority,
      target_type: notice.target_type,
      target_ids: targetIds,
      is_pinned: Boolean(notice.is_pinned),
      require_acknowledgment: Boolean(notice.require_acknowledgment),
      allow_comments: Boolean(notice.allow_comments),
      status: notice.status,
    });
    setModalOpen(true);
  };

  useEffect(() => {
    if (form.data.target_type === 'all') {
      setTargetOptions([]);
      return;
    }

    setLoadingOptions(true);
    fetch(`/notice-board/notices/target-options?type=${form.data.target_type}`)
      .then((res) => res.json())
      .then((data) => {
        setTargetOptions(data || []);
      })
      .catch(() => setTargetOptions([]))
      .finally(() => setLoadingOptions(false));
  }, [form.data.target_type]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingNotice) {
      form.put(`/notice-board/notices/${editingNotice.id}`, {
        onSuccess: () => {
          setModalOpen(false);
          form.reset();
        },
      });
    } else {
      form.post('/notice-board/notices', {
        onSuccess: () => {
          setModalOpen(false);
          form.reset();
        },
      });
    }
  };

  const handleTogglePin = (notice: NoticeItem) => {
    router.post(`/notice-board/notices/${notice.id}/toggle-pin`, {}, { preserveScroll: true });
  };

  const handlePublish = (notice: NoticeItem) => {
    router.post(`/notice-board/notices/${notice.id}/publish`, {}, { preserveScroll: true });
  };

  const handleDeactivate = (notice: NoticeItem) => {
    router.post(`/notice-board/notices/${notice.id}/deactivate`, {}, { preserveScroll: true });
  };

  const handleDelete = (notice: NoticeItem) => {
    if (confirm(`Are you sure you want to delete notice "${notice.title}"?`)) {
      router.delete(`/notice-board/notices/${notice.id}`, { preserveScroll: true });
    }
  };

  const columns: Column<NoticeItem>[] = [
    {
      header: 'Title',
      render: (row) => (
        <div className="space-y-1">
          <div className="flex items-center gap-1.5">
            {row.is_pinned && <Pin className="w-3.5 h-3.5 text-amber-400 flex-shrink-0" />}
            <Link
              href={`/notice-board/notices/${row.id}`}
              className="text-sm font-medium text-[var(--text-primary)] hover:underline"
            >
              {row.title}
            </Link>
          </div>
          <div className="text-xs text-[var(--text-tertiary)]">
            By {row.creator?.name || 'System'}
          </div>
        </div>
      ),
    },
    {
      header: 'Priority',
      render: (row) => {
        if (row.priority === 'critical') return <Badge variant="rose">Critical</Badge>;
        if (row.priority === 'urgent') return <Badge variant="amber">Urgent</Badge>;
        return <Badge variant="neutral">Normal</Badge>;
      },
    },
    {
      header: 'Status',
      render: (row) => {
        if (row.status === 'published') return <Badge variant="emerald">Published</Badge>;
        if (row.status === 'draft') return <Badge variant="neutral">Draft</Badge>;
        return <Badge variant="rose">Deactivated</Badge>;
      },
    },
    {
      header: 'Targeting',
      render: (row) => {
        const labels: Record<string, string> = {
          all: 'All Members',
          department: 'Department',
          role: 'Role',
          specific_users: 'Specific Users',
        };
        return <span className="text-xs text-[var(--text-secondary)]">{labels[row.target_type] || row.target_type}</span>;
      },
    },
    {
      header: 'Dates',
      render: (row) => (
        <div className="text-xs text-[var(--text-secondary)]">
          <div>{formatDate(row.start_date)}</div>
          {row.expiry_date ? (
            <div className="text-[var(--text-tertiary)] text-[11px]">Exp: {formatDate(row.expiry_date)}</div>
          ) : (
            <div className="text-[var(--text-tertiary)] text-[11px]">No expiry</div>
          )}
        </div>
      ),
    },
    {
      header: 'Engagement',
      render: (row) => (
        <div className="flex items-center gap-3 text-xs text-[var(--text-secondary)]">
          <span className="flex items-center gap-1">
            <Eye className="w-3.5 h-3.5 text-[var(--text-tertiary)]" /> {row.reads_count ?? 0}
          </span>
          {row.allow_comments && (
            <span className="flex items-center gap-1">
              <MessageSquare className="w-3.5 h-3.5 text-[var(--text-tertiary)]" /> {row.comments_count ?? 0}
            </span>
          )}
        </div>
      ),
    },
    {
      header: 'Actions',
      align: 'right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <button
            onClick={() => handleTogglePin(row)}
            title={row.is_pinned ? 'Unpin' : 'Pin'}
            className={`p-1.5 rounded-lg hover:bg-[var(--surface-3)] transition-colors ${
              row.is_pinned ? 'text-amber-400' : 'text-[var(--text-tertiary)] hover:text-[var(--text-primary)]'
            }`}
          >
            <Pin className="w-4 h-4" />
          </button>

          {row.status === 'published' ? (
            <button
              onClick={() => handleDeactivate(row)}
              title="Deactivate notice"
              className="p-1.5 rounded-lg text-amber-400/80 hover:text-amber-300 hover:bg-[var(--surface-3)] transition-colors"
            >
              <XCircle className="w-4 h-4" />
            </button>
          ) : (
            <button
              onClick={() => handlePublish(row)}
              title="Publish notice"
              className="p-1.5 rounded-lg text-emerald-400/80 hover:text-emerald-300 hover:bg-[var(--surface-3)] transition-colors"
            >
              <CheckCircle className="w-4 h-4" />
            </button>
          )}

          <button
            onClick={() => openEditModal(row)}
            title="Edit notice"
            className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface-3)] transition-colors"
          >
            <Edit2 className="w-4 h-4" />
          </button>

          <button
            onClick={() => handleDelete(row)}
            title="Delete notice"
            className="p-1.5 rounded-lg text-rose-400/80 hover:text-rose-300 hover:bg-[var(--surface-3)] transition-colors"
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Manage Notices">
      <Head title="Manage Notices" />

      <div className="space-y-6 pb-12">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Link href="/notice-board/board">
              <Button variant="outline" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Board View
              </Button>
            </Link>
            <div>
              <h1 className="text-xl font-semibold text-[var(--text-primary)]">Manage Notices</h1>
              <p className="text-sm text-[var(--text-secondary)] mt-0.5">
                Create, update, schedule, and monitor notice engagement.
              </p>
            </div>
          </div>

          <Button variant="neutral" onClick={openCreateModal} icon={<Plus className="w-4 h-4" />}>
            Create Notice
          </Button>
        </div>

        {/* Notices Table */}
        <DataTable
          data={notices}
          columns={columns}
          keyExtractor={(row) => row.id}
          searchPlaceholder="Search notices by title..."
          emptyTitle="No notices created yet"
          emptyDescription="Create your first announcement to share updates with your organization."
        />
      </div>

      {/* Create / Edit Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingNotice ? 'Edit Notice' : 'Create New Notice'}
        description="Publish organization-wide or targeted announcements."
        maxWidth="2xl"
      >
        <form onSubmit={handleSubmit} className="space-y-4 pt-2">
          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Title <span className="text-rose-400">*</span>
            </label>
            <input
              type="text"
              required
              value={form.data.title}
              onChange={(e) => form.setData('title', e.target.value)}
              placeholder="e.g., Office Maintenance Schedule"
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)]"
            />
            {form.errors.title && (
              <p className="text-xs text-rose-400 mt-1">{form.errors.title}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Content / Description
            </label>
            <textarea
              rows={4}
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              placeholder="Provide complete details about this announcement..."
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)] resize-y"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                Start Date <span className="text-rose-400">*</span>
              </label>
              <input
                type="date"
                required
                value={form.data.start_date}
                onChange={(e) => form.setData('start_date', e.target.value)}
                className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)]"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                Expiry Date (Optional)
              </label>
              <input
                type="date"
                value={form.data.expiry_date}
                onChange={(e) => form.setData('expiry_date', e.target.value)}
                className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)]"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                Priority
              </label>
              <select
                value={form.data.priority}
                onChange={(e) => form.setData('priority', e.target.value as any)}
                className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
              >
                <option value="normal">Normal</option>
                <option value="urgent">Urgent</option>
                <option value="critical">Critical</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                Target Audience
              </label>
              <select
                value={form.data.target_type}
                onChange={(e) => {
                  form.setData((prev) => ({
                    ...prev,
                    target_type: e.target.value as any,
                    target_ids: [],
                  }));
                }}
                className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
              >
                <option value="all">All Members</option>
                <option value="department">Department</option>
                <option value="role">Role</option>
                <option value="specific_users">Specific Users</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                Status
              </label>
              <select
                value={form.data.status}
                onChange={(e) => form.setData('status', e.target.value as any)}
                className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
              >
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="deactivated">Deactivated</option>
              </select>
            </div>
          </div>

          {/* Dynamic Target Selection */}
          {form.data.target_type !== 'all' && (
            <div className="p-3 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] space-y-2">
              <label className="block text-xs font-medium text-[var(--text-secondary)]">
                Select Targets {loadingOptions && '(Loading options...)'}
              </label>
              {targetOptions.length === 0 && !loadingOptions ? (
                <p className="text-xs text-[var(--text-tertiary)]">No available options found.</p>
              ) : (
                <div className="max-h-36 overflow-y-auto grid grid-cols-2 gap-2 text-xs">
                  {targetOptions.map((opt) => {
                    const checked = form.data.target_ids.includes(opt.id);
                    return (
                      <label key={opt.id} className="flex items-center gap-2 text-[var(--text-secondary)] cursor-pointer">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={(e) => {
                            if (e.target.checked) {
                              form.setData('target_ids', [...form.data.target_ids, opt.id]);
                            } else {
                              form.setData('target_ids', form.data.target_ids.filter((id) => id !== opt.id));
                            }
                          }}
                          className="rounded border-[var(--border-medium)] bg-[var(--surface-2)]"
                        />
                        <span className="truncate">{opt.name}</span>
                      </label>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* Options checkboxes */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
            <label className="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
              <input
                type="checkbox"
                checked={form.data.is_pinned}
                onChange={(e) => form.setData('is_pinned', e.target.checked)}
                className="rounded border-[var(--border-medium)] bg-[var(--surface-2)]"
              />
              Pin to top of board
            </label>

            <label className="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
              <input
                type="checkbox"
                checked={form.data.require_acknowledgment}
                onChange={(e) => form.setData('require_acknowledgment', e.target.checked)}
                className="rounded border-[var(--border-medium)] bg-[var(--surface-2)]"
              />
              Require acknowledgment
            </label>

            <label className="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
              <input
                type="checkbox"
                checked={form.data.allow_comments}
                onChange={(e) => form.setData('allow_comments', e.target.checked)}
                className="rounded border-[var(--border-medium)] bg-[var(--surface-2)]"
              />
              Allow comments
            </label>
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[var(--border-subtle)]">
            <Button
              type="button"
              variant="outline"
              onClick={() => setModalOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="neutral"
              loading={form.processing}
            >
              {editingNotice ? 'Update Notice' : 'Create Notice'}
            </Button>
          </div>
        </form>
      </Modal>
    </AppShell>
  );
}
