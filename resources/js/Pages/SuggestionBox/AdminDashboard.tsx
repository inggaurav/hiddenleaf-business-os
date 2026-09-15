import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
  Lightbulb,
  CheckCircle2,
  Clock,
  ThumbsUp,
  Eye,
  Send,
  Trash2,
  Filter,
  ArrowLeft,
  FolderTree,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Card } from '@/Components/UI/Card';
import { Modal } from '@/Components/UI/Modal';
import { DataTable, Column, LaravelPaginator } from '@/Components/UI/DataTable';
import { formatDate } from '@/lib/format';
import { SuggestionItem, SuggestionCategoryItem } from './types';

interface AdminDashboardProps {
  suggestions: LaravelPaginator<SuggestionItem>;
  categories: SuggestionCategoryItem[];
  stats: {
    total: number;
    new: number;
    under_review: number;
    accepted: number;
    rejected: number;
    complete: number;
  };
  filters: {
    search?: string;
    status?: string;
    category_id?: string;
  };
}

export default function AdminDashboard({
  suggestions,
  categories,
  stats,
  filters,
}: AdminDashboardProps) {
  const [selectedSuggestion, setSelectedSuggestion] = useState<SuggestionItem | null>(null);
  const [responseModalOpen, setResponseModalOpen] = useState(false);

  const responseForm = useForm({
    status: 'under_review',
    admin_response: '',
  });

  const openResponseModal = (suggestion: SuggestionItem) => {
    setSelectedSuggestion(suggestion);
    responseForm.setData({
      status: suggestion.status,
      admin_response: suggestion.admin_response || '',
    });
    setResponseModalOpen(true);
  };

  const handleResponseSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSuggestion) return;

    responseForm.post(`/suggestion-box/suggestions/${selectedSuggestion.id}/respond`, {
      onSuccess: () => setResponseModalOpen(false),
    });
  };

  const handleDelete = (suggestion: SuggestionItem) => {
    if (confirm(`Delete suggestion "${suggestion.title}"?`)) {
      router.delete(`/suggestion-box/suggestions/${suggestion.id}`, {
        preserveScroll: true,
      });
    }
  };

  const statusBadge = (status: string) => {
    switch (status) {
      case 'new':
        return <Badge variant="neutral">New</Badge>;
      case 'under_review':
        return <Badge variant="amber">Under Review</Badge>;
      case 'accepted':
        return <Badge variant="emerald">Accepted</Badge>;
      case 'complete':
        return <Badge variant="brand">Complete</Badge>;
      case 'rejected':
        return <Badge variant="rose">Rejected</Badge>;
      default:
        return <Badge variant="neutral">{status}</Badge>;
    }
  };

  const columns: Column<SuggestionItem>[] = [
    {
      header: 'Suggestion',
      render: (row) => (
        <div className="space-y-1">
          <Link
            href={`/suggestion-box/suggestions/${row.id}`}
            className="text-sm font-medium text-[var(--text-primary)] hover:underline"
          >
            {row.title}
          </Link>
          <div className="text-xs text-[var(--text-tertiary)]">
            By {row.author_display}
          </div>
        </div>
      ),
    },
    {
      header: 'Category',
      render: (row) => row.category ? (
        <span className="text-xs font-medium text-[var(--text-secondary)] flex items-center gap-1.5">
          <span className="w-2 h-2 rounded-full" style={{ backgroundColor: row.category.color }} />
          {row.category.name}
        </span>
      ) : (
        <span className="text-xs text-[var(--text-tertiary)]">General</span>
      ),
    },
    {
      header: 'Engagement',
      render: (row) => (
        <div className="flex items-center gap-3 text-xs text-[var(--text-secondary)]">
          <span className="flex items-center gap-1">
            <ThumbsUp className="w-3.5 h-3.5 text-emerald-400" /> {row.votes_count}
          </span>
          <span className="flex items-center gap-1">
            <Eye className="w-3.5 h-3.5 text-[var(--text-tertiary)]" /> {row.views_count}
          </span>
        </div>
      ),
    },
    {
      header: 'Status',
      render: (row) => statusBadge(row.status),
    },
    {
      header: 'Submitted',
      render: (row) => (
        <span className="text-xs text-[var(--text-secondary)]">{formatDate(row.created_at)}</span>
      ),
    },
    {
      header: 'Actions',
      align: 'right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Button
            variant="outline"
            size="sm"
            onClick={() => openResponseModal(row)}
            icon={<Send className="w-3.5 h-3.5" />}
          >
            Review
          </Button>
          <button
            onClick={() => handleDelete(row)}
            title="Delete"
            className="p-1.5 rounded-lg text-rose-400/80 hover:text-rose-300 hover:bg-[var(--surface-3)] transition-colors"
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Suggestion Box Admin">
      <Head title="Suggestion Box Admin" />

      <div className="space-y-6 pb-12">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Link href="/suggestion-box">
              <Button variant="outline" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Public View
              </Button>
            </Link>
            <div>
              <h1 className="text-xl font-semibold text-[var(--text-primary)]">
                Suggestion Box Administration
              </h1>
              <p className="text-sm text-[var(--text-secondary)] mt-0.5">
                Review submitted ideas, update evaluation states, and post leadership feedback.
              </p>
            </div>
          </div>

          <Link href="/suggestion-box/categories">
            <Button variant="outline" icon={<FolderTree className="w-4 h-4" />}>
              Manage Categories
            </Button>
          </Link>
        </div>

        {/* Stats Row */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <Card level={1} className="text-center p-3">
            <div className="text-xs text-[var(--text-tertiary)]">Total</div>
            <div className="text-xl font-bold text-[var(--text-primary)] mt-1">{stats.total}</div>
          </Card>
          <Card level={1} className="text-center p-3">
            <div className="text-xs text-[var(--text-tertiary)]">New</div>
            <div className="text-xl font-bold text-[var(--text-primary)] mt-1">{stats.new}</div>
          </Card>
          <Card level={1} className="text-center p-3">
            <div className="text-xs text-amber-400">Under Review</div>
            <div className="text-xl font-bold text-amber-300 mt-1">{stats.under_review}</div>
          </Card>
          <Card level={1} className="text-center p-3">
            <div className="text-xs text-emerald-400">Accepted</div>
            <div className="text-xl font-bold text-emerald-300 mt-1">{stats.accepted}</div>
          </Card>
          <Card level={1} className="text-center p-3">
            <div className="text-xs text-indigo-400">Implemented</div>
            <div className="text-xl font-bold text-indigo-300 mt-1">{stats.complete}</div>
          </Card>
          <Card level={1} className="text-center p-3">
            <div className="text-xs text-rose-400">Declined</div>
            <div className="text-xl font-bold text-rose-300 mt-1">{stats.rejected}</div>
          </Card>
        </div>

        {/* DataTable */}
        <DataTable
          data={suggestions}
          columns={columns}
          keyExtractor={(row) => row.id}
          searchPlaceholder="Search suggestions..."
          emptyTitle="No suggestions submitted yet"
          emptyDescription="Employee ideas and suggestions will appear here for management review."
        />
      </div>

      {/* Response Modal */}
      <Modal
        isOpen={responseModalOpen}
        onClose={() => setResponseModalOpen(false)}
        title={`Review: ${selectedSuggestion?.title || ''}`}
        description="Update suggestion status and submit formal leadership response."
        maxWidth="lg"
      >
        <form onSubmit={handleResponseSubmit} className="space-y-4 pt-2">
          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Status Decision <span className="text-rose-400">*</span>
            </label>
            <select
              required
              value={responseForm.data.status}
              onChange={(e) => responseForm.setData('status', e.target.value as any)}
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
            >
              <option value="new">New</option>
              <option value="under_review">Under Review</option>
              <option value="accepted">Accepted (Planned)</option>
              <option value="complete">Complete (Implemented)</option>
              <option value="rejected">Rejected (Declined)</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Leadership Response / Feedback
            </label>
            <textarea
              rows={4}
              value={responseForm.data.admin_response}
              onChange={(e) => responseForm.setData('admin_response', e.target.value)}
              placeholder="Provide context on this decision to share with the submitter..."
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)]"
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[var(--border-subtle)]">
            <Button
              type="button"
              variant="outline"
              onClick={() => setResponseModalOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="neutral"
              loading={responseForm.processing}
            >
              Save Decision
            </Button>
          </div>
        </form>
      </Modal>
    </AppShell>
  );
}
