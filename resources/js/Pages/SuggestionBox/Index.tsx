import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
  ThumbsUp,
  MessageSquare,
  Eye,
  Plus,
  Search,
  Filter,
  Flame,
  Clock,
  CheckCircle,
  Lightbulb,
  Shield,
  ArrowRight,
  ShieldCheck,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Card } from '@/Components/UI/Card';
import { Modal } from '@/Components/UI/Modal';
import { LaravelPaginator } from '@/Components/UI/DataTable';
import { formatDate, formatRelative } from '@/lib/format';
import { SuggestionItem, SuggestionCategoryItem } from './types';

interface IndexProps {
  suggestions: LaravelPaginator<SuggestionItem>;
  categories: SuggestionCategoryItem[];
  canManage: boolean;
  filters: {
    search?: string;
    status?: string;
    category_id?: string;
    sort?: string;
  };
}

export default function Index({ suggestions, categories, canManage, filters }: IndexProps) {
  const [modalOpen, setModalOpen] = useState(false);
  const [votingId, setVotingId] = useState<number | null>(null);

  const form = useForm({
    title: '',
    category_id: '' as string | number,
    description: '',
    is_anonymous: false,
  });

  const handleVote = (e: React.MouseEvent, suggestionId: number) => {
    e.stopPropagation();
    e.preventDefault();
    setVotingId(suggestionId);
    router.post(
      `/suggestion-box/suggestions/${suggestionId}/vote`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setVotingId(null),
      }
    );
  };

  const handleFilterChange = (key: string, value: string) => {
    const params = new URLSearchParams(window.location.search);
    if (value && value !== 'all') {
      params.set(key, value);
    } else {
      params.delete(key);
    }
    params.delete('page');
    router.get(`/suggestion-box?${params.toString()}`, {}, { preserveState: true, preserveScroll: true });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post('/suggestion-box/suggestions', {
      onSuccess: () => {
        setModalOpen(false);
        form.reset();
      },
    });
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

  return (
    <AppShell title="Suggestion Box">
      <Head title="Suggestion Box" />

      <div className="space-y-6 pb-12">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Suggestion Box</h1>
            <p className="text-sm text-[var(--text-secondary)] mt-0.5">
              Submit ideas, upvote peer contributions, and shape the company's roadmap.
            </p>
          </div>
          <div className="flex items-center gap-2">
            {canManage && (
              <Link href="/suggestion-box/admin">
                <Button variant="outline">
                  Admin Review
                </Button>
              </Link>
            )}
            <Button variant="neutral" onClick={() => setModalOpen(true)} icon={<Plus className="w-4 h-4" />}>
              Share Suggestion
            </Button>
          </div>
        </div>

        {/* Filter Controls */}
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-2xl bg-[var(--surface-1)] border border-[var(--border-subtle)]">
          <div className="flex flex-wrap items-center gap-2 flex-1">
            {/* Category pills */}
            <button
              onClick={() => handleFilterChange('category_id', 'all')}
              className={`px-3 py-1.5 rounded-xl text-xs font-medium cursor-pointer transition-colors ${
                !filters.category_id || filters.category_id === 'all'
                  ? 'bg-[var(--surface-3)] text-[var(--text-primary)] border border-[var(--border-strong)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-transparent'
              }`}
            >
              All Categories
            </button>
            {categories.map((cat) => (
              <button
                key={cat.id}
                onClick={() => handleFilterChange('category_id', String(cat.id))}
                className={`px-3 py-1.5 rounded-xl text-xs font-medium cursor-pointer transition-colors flex items-center gap-1.5 ${
                  filters.category_id === String(cat.id)
                    ? 'bg-[var(--surface-3)] text-[var(--text-primary)] border border-[var(--border-strong)]'
                    : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-transparent'
                }`}
              >
                <span className="w-2 h-2 rounded-full" style={{ backgroundColor: cat.color }} />
                {cat.name}
              </button>
            ))}
          </div>

          <div className="flex items-center gap-2">
            {/* Status select */}
            <select
              value={filters.status || 'all'}
              onChange={(e) => handleFilterChange('status', e.target.value)}
              className="px-3 py-1.5 rounded-xl text-xs bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-secondary)] focus:outline-none"
            >
              <option value="all">All Statuses</option>
              <option value="new">New</option>
              <option value="under_review">Under Review</option>
              <option value="accepted">Accepted</option>
              <option value="complete">Complete</option>
              <option value="rejected">Rejected</option>
            </select>

            {/* Sort select */}
            <select
              value={filters.sort || 'latest'}
              onChange={(e) => handleFilterChange('sort', e.target.value)}
              className="px-3 py-1.5 rounded-xl text-xs bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-secondary)] focus:outline-none"
            >
              <option value="latest">Latest</option>
              <option value="votes">Most Upvoted</option>
            </select>
          </div>
        </div>

        {/* Suggestion Cards Grid */}
        {suggestions.data.length === 0 ? (
          <Card level={1} className="text-center py-16">
            <div className="max-w-md mx-auto space-y-3">
              <div className="w-12 h-12 rounded-2xl bg-[var(--surface-2)] flex items-center justify-center mx-auto text-[var(--text-tertiary)]">
                <Lightbulb className="w-6 h-6" />
              </div>
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">No suggestions found</h3>
              <p className="text-xs text-[var(--text-secondary)]">
                Be the first to share an innovative improvement or new idea for the team!
              </p>
              <Button variant="neutral" size="sm" onClick={() => setModalOpen(true)} icon={<Plus className="w-3.5 h-3.5" />}>
                Submit Idea
              </Button>
            </div>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {suggestions.data.map((item) => (
              <Card
                key={item.id}
                level={1}
                className="flex flex-col justify-between hover:border-[var(--border-strong)] transition-all group"
              >
                <div className="space-y-3">
                  {/* Top tags */}
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      {statusBadge(item.status)}
                      {item.category && (
                        <span className="text-[11px] font-medium text-[var(--text-secondary)] flex items-center gap-1.5">
                          <span
                            className="w-2 h-2 rounded-full"
                            style={{ backgroundColor: item.category.color }}
                          />
                          {item.category.name}
                        </span>
                      )}
                      {item.is_anonymous && (
                        <span className="text-[10px] text-[var(--text-tertiary)] flex items-center gap-1">
                          <ShieldCheck className="w-3 h-3 text-indigo-400" /> Anonymous
                        </span>
                      )}
                    </div>
                    <span className="text-[11px] text-[var(--text-tertiary)]">
                      {formatRelative(item.created_at)}
                    </span>
                  </div>

                  {/* Title & Preview */}
                  <div>
                    <Link
                      href={`/suggestion-box/suggestions/${item.id}`}
                      className="text-base font-semibold text-[var(--text-primary)] group-hover:underline line-clamp-1"
                    >
                      {item.title}
                    </Link>
                    <p className="text-xs text-[var(--text-secondary)] mt-1.5 line-clamp-2 leading-relaxed">
                      {item.description}
                    </p>
                  </div>

                  {/* Admin response snippet if present */}
                  {item.admin_response && (
                    <div className="p-2.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-xs text-[var(--text-secondary)]">
                      <div className="text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-0.5">
                        Team Feedback
                      </div>
                      <p className="line-clamp-1 italic">"{item.admin_response}"</p>
                    </div>
                  )}
                </div>

                {/* Footer Controls */}
                <div className="mt-4 pt-3 border-t border-[var(--border-subtle)] flex items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <button
                      onClick={(e) => handleVote(e, item.id)}
                      disabled={votingId === item.id}
                      className={`px-3 py-1 rounded-xl text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer ${
                        item.has_voted
                          ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                          : 'bg-[var(--surface-2)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-[var(--border-subtle)]'
                      }`}
                    >
                      <ThumbsUp className={`w-3.5 h-3.5 ${item.has_voted ? 'fill-emerald-400' : ''}`} />
                      <span>{item.votes_count}</span>
                    </button>
                    <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">
                      <Eye className="w-3.5 h-3.5" /> {item.views_count}
                    </span>
                  </div>

                  <div className="flex items-center gap-2">
                    <span className="text-xs text-[var(--text-tertiary)]">
                      by {item.author_display}
                    </span>
                    <Link href={`/suggestion-box/suggestions/${item.id}`}>
                      <Button variant="outline" size="sm" icon={<ArrowRight className="w-3.5 h-3.5" />} iconPosition="right">
                        View
                      </Button>
                    </Link>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>

      {/* Submit Suggestion Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title="Share Your Idea"
        description="Submit a suggestion for company improvement or product features."
        maxWidth="lg"
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
              placeholder="e.g., Weekly knowledge sharing sessions"
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)]"
            />
            {form.errors.title && (
              <p className="text-xs text-rose-400 mt-1">{form.errors.title}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Category
            </label>
            <select
              value={form.data.category_id}
              onChange={(e) => form.setData('category_id', e.target.value)}
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
            >
              <option value="">General / Uncategorized</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Description <span className="text-rose-400">*</span>
            </label>
            <textarea
              rows={4}
              required
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              placeholder="Describe the problem, proposed solution, and benefits to the organization..."
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)]"
            />
          </div>

          <div className="pt-2">
            <label className="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
              <input
                type="checkbox"
                checked={form.data.is_anonymous}
                onChange={(e) => form.setData('is_anonymous', e.target.checked)}
                className="rounded border-[var(--border-medium)] bg-[var(--surface-2)]"
              />
              <span>Submit anonymously (hides your name from peers)</span>
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
              Submit Suggestion
            </Button>
          </div>
        </form>
      </Modal>
    </AppShell>
  );
}
