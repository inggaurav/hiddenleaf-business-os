import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
  ArrowLeft,
  ThumbsUp,
  Eye,
  Calendar,
  Clock,
  ShieldCheck,
  CheckCircle,
  MessageSquare,
  History,
  Edit2,
  Trash2,
  Send,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Card } from '@/Components/UI/Card';
import { Modal } from '@/Components/UI/Modal';
import { formatDate, formatDateTime } from '@/lib/format';
import { SuggestionItem } from './types';

interface ShowProps {
  suggestion: SuggestionItem;
  hasVoted: boolean;
  canManage: boolean;
  canEdit: boolean;
}

export default function Show({ suggestion, hasVoted, canManage, canEdit }: ShowProps) {
  const [responseModalOpen, setResponseModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [voting, setVoting] = useState(false);

  const responseForm = useForm({
    status: suggestion.status,
    admin_response: suggestion.admin_response || '',
  });

  const editForm = useForm({
    title: suggestion.title,
    description: suggestion.description,
    category_id: suggestion.category_id || '',
    is_anonymous: suggestion.is_anonymous,
  });

  const handleVote = () => {
    setVoting(true);
    router.post(
      `/suggestion-box/suggestions/${suggestion.id}/vote`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setVoting(false),
      }
    );
  };

  const handleResponseSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    responseForm.post(`/suggestion-box/suggestions/${suggestion.id}/respond`, {
      onSuccess: () => setResponseModalOpen(false),
    });
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    editForm.put(`/suggestion-box/suggestions/${suggestion.id}`, {
      onSuccess: () => setEditModalOpen(false),
    });
  };

  const handleDelete = () => {
    if (confirm('Are you sure you want to delete this suggestion?')) {
      router.delete(`/suggestion-box/suggestions/${suggestion.id}`, {
        onSuccess: () => router.visit('/suggestion-box'),
      });
    }
  };

  const statusBadge = (status: string) => {
    switch (status) {
      case 'new':
        return <Badge variant="neutral">New Idea</Badge>;
      case 'under_review':
        return <Badge variant="amber">Under Review</Badge>;
      case 'accepted':
        return <Badge variant="emerald">Accepted</Badge>;
      case 'complete':
        return <Badge variant="brand">Implemented</Badge>;
      case 'rejected':
        return <Badge variant="rose">Declined</Badge>;
      default:
        return <Badge variant="neutral">{status}</Badge>;
    }
  };

  return (
    <AppShell title={suggestion.title}>
      <Head title={suggestion.title} />

      <div className="max-w-4xl mx-auto space-y-6 pb-16">
        {/* Navigation bar */}
        <div className="flex items-center justify-between gap-4">
          <Link href="/suggestion-box">
            <Button variant="outline" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
              Back to Suggestions
            </Button>
          </Link>

          <div className="flex items-center gap-2">
            {canEdit && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setEditModalOpen(true)}
                icon={<Edit2 className="w-3.5 h-3.5" />}
              >
                Edit
              </Button>
            )}

            {canManage && (
              <>
                <Button
                  variant="neutral"
                  size="sm"
                  onClick={() => setResponseModalOpen(true)}
                  icon={<Send className="w-3.5 h-3.5" />}
                >
                  Respond & Update Status
                </Button>
                <Button
                  variant="danger"
                  size="sm"
                  onClick={handleDelete}
                  icon={<Trash2 className="w-3.5 h-3.5" />}
                >
                  Delete
                </Button>
              </>
            )}
          </div>
        </div>

        {/* Main Suggestion Card */}
        <Card level={1} className="space-y-6">
          <div className="space-y-3 border-b border-[var(--border-subtle)] pb-5">
            <div className="flex flex-wrap items-center gap-2">
              {statusBadge(suggestion.status)}
              {suggestion.category && (
                <span className="text-xs font-medium text-[var(--text-secondary)] flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-[var(--surface-2)]">
                  <span
                    className="w-2 h-2 rounded-full"
                    style={{ backgroundColor: suggestion.category.color }}
                  />
                  {suggestion.category.name}
                </span>
              )}
              {suggestion.is_anonymous && (
                <Badge variant="brand" size="sm">
                  <ShieldCheck className="w-3 h-3 mr-1 inline" /> Anonymous Submission
                </Badge>
              )}
            </div>

            <h1 className="text-2xl font-bold text-[var(--text-primary)] leading-snug">
              {suggestion.title}
            </h1>

            <div className="flex flex-wrap items-center gap-4 text-xs text-[var(--text-tertiary)] pt-1">
              <span>
                By <strong className="text-[var(--text-secondary)]">{suggestion.author_display}</strong>
              </span>
              <span className="flex items-center gap-1">
                <Calendar className="w-3.5 h-3.5" />
                Submitted {formatDate(suggestion.created_at)}
              </span>
              <span className="flex items-center gap-1">
                <Eye className="w-3.5 h-3.5" />
                {suggestion.views_count} views
              </span>
            </div>
          </div>

          {/* Description */}
          <div className="text-[var(--text-primary)] text-sm leading-relaxed whitespace-pre-wrap">
            {suggestion.description}
          </div>

          {/* Upvote CTA Bar */}
          <div className="p-4 rounded-2xl bg-[var(--surface-2)] border border-[var(--border-medium)] flex items-center justify-between gap-4">
            <div className="space-y-0.5">
              <h4 className="text-xs font-semibold text-[var(--text-primary)]">
                Community Feedback
              </h4>
              <p className="text-xs text-[var(--text-secondary)]">
                Upvote this idea to increase its visibility and prioritize it for team review.
              </p>
            </div>

            <Button
              variant={hasVoted ? 'neutral' : 'outline'}
              onClick={handleVote}
              loading={voting}
              icon={<ThumbsUp className={`w-4 h-4 ${hasVoted ? 'text-emerald-400 fill-emerald-400' : ''}`} />}
            >
              {hasVoted ? `Upvoted (${suggestion.votes_count})` : `Upvote (${suggestion.votes_count})`}
            </Button>
          </div>
        </Card>

        {/* Team Response Card if present */}
        {suggestion.admin_response && (
          <Card level={2} className="space-y-3 border-emerald-500/20 bg-emerald-500/5">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-emerald-400 flex items-center gap-2">
                <CheckCircle className="w-4 h-4" />
                Leadership Response
              </h3>
              {suggestion.responded_at && (
                <span className="text-[11px] text-[var(--text-tertiary)]">
                  {formatDateTime(suggestion.responded_at)}
                </span>
              )}
            </div>

            <p className="text-xs text-[var(--text-primary)] leading-relaxed whitespace-pre-wrap">
              {suggestion.admin_response}
            </p>

            {suggestion.responded_by && (
              <div className="text-[11px] text-[var(--text-tertiary)] pt-1">
                Reviewed by {suggestion.responded_by.name}
              </div>
            )}
          </Card>
        )}

        {/* Status History Timeline */}
        {suggestion.status_histories && suggestion.status_histories.length > 0 && (
          <Card level={1} className="space-y-4">
            <h3 className="text-sm font-semibold text-[var(--text-primary)] flex items-center gap-2">
              <History className="w-4 h-4 text-[var(--text-secondary)]" />
              Status Transition History
            </h3>

            <div className="space-y-3 border-l border-[var(--border-subtle)] pl-4 ml-2">
              {suggestion.status_histories.map((hist) => (
                <div key={hist.id} className="space-y-1 relative">
                  <div className="w-2 h-2 rounded-full bg-[var(--border-strong)] absolute -left-[21px] top-1.5" />
                  <div className="flex items-center gap-2 text-xs">
                    <span className="font-semibold text-[var(--text-primary)] capitalize">
                      {hist.new_status.replace('_', ' ')}
                    </span>
                    <span className="text-[11px] text-[var(--text-tertiary)]">
                      from {hist.old_status.replace('_', ' ')} • {formatDateTime(hist.created_at)}
                    </span>
                  </div>
                  {hist.comment && (
                    <p className="text-xs text-[var(--text-secondary)] italic">
                      "{hist.comment}"
                    </p>
                  )}
                  {hist.changed_by && (
                    <div className="text-[10px] text-[var(--text-tertiary)]">
                      By {hist.changed_by.name}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </Card>
        )}
      </div>

      {/* Admin Response Modal */}
      <Modal
        isOpen={responseModalOpen}
        onClose={() => setResponseModalOpen(false)}
        title="Respond to Suggestion"
        description="Update suggestion status and leave feedback for the team."
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
              Feedback / Reason
            </label>
            <textarea
              rows={4}
              value={responseForm.data.admin_response}
              onChange={(e) => responseForm.setData('admin_response', e.target.value)}
              placeholder="Explain the rationale behind this status update..."
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
              Save Response
            </Button>
          </div>
        </form>
      </Modal>

      {/* Edit Suggestion Modal */}
      <Modal
        isOpen={editModalOpen}
        onClose={() => setEditModalOpen(false)}
        title="Edit Suggestion"
        maxWidth="lg"
      >
        <form onSubmit={handleEditSubmit} className="space-y-4 pt-2">
          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Title
            </label>
            <input
              type="text"
              required
              value={editForm.data.title}
              onChange={(e) => editForm.setData('title', e.target.value)}
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Description
            </label>
            <textarea
              rows={4}
              required
              value={editForm.data.description}
              onChange={(e) => editForm.setData('description', e.target.value)}
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
            />
          </div>

          <div className="pt-2">
            <label className="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
              <input
                type="checkbox"
                checked={editForm.data.is_anonymous}
                onChange={(e) => editForm.setData('is_anonymous', e.target.checked)}
                className="rounded border-[var(--border-medium)] bg-[var(--surface-2)]"
              />
              <span>Submit anonymously</span>
            </label>
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[var(--border-subtle)]">
            <Button
              type="button"
              variant="outline"
              onClick={() => setEditModalOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="neutral"
              loading={editForm.processing}
            >
              Update Suggestion
            </Button>
          </div>
        </form>
      </Modal>
    </AppShell>
  );
}
