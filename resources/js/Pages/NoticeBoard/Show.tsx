import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
  ArrowLeft,
  Calendar,
  Clock,
  Pin,
  CheckCircle2,
  AlertCircle,
  MessageSquare,
  Eye,
  Send,
  Trash2,
  CornerDownRight,
  Download,
  Users,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Card } from '@/Components/UI/Card';
import { formatDate, formatDateTime } from '@/lib/format';
import { NoticeItem, NoticeCommentItem } from './types';

interface ShowProps {
  notice: NoticeItem;
  comments: NoticeCommentItem[];
  readStats: {
    reads: Array<{
      id: number;
      user_id: number;
      read_at: string | null;
      acknowledged_at: string | null;
      user?: { id: number; name: string };
    }>;
    read_count: number;
    acknowledged_count: number;
  } | null;
  userRead: {
    read_at: string | null;
    acknowledged_at: string | null;
  } | null;
  userAcknowledged: boolean;
  canManage: boolean;
}

export default function Show({
  notice,
  comments,
  readStats,
  userRead,
  userAcknowledged,
  canManage,
}: ShowProps) {
  const [replyToId, setReplyToId] = useState<number | null>(null);
  const [statsOpen, setStatsOpen] = useState(false);

  const commentForm = useForm({
    comment: '',
    parent_id: null as number | null,
  });

  const page = usePage<any>();
  const currentUserId = page.props.auth?.user?.id;

  const handlePostComment = (e: React.FormEvent) => {
    e.preventDefault();
    commentForm.post(`/notice-board/notices/${notice.id}/comments`, {
      preserveScroll: true,
      onSuccess: () => {
        commentForm.reset();
        setReplyToId(null);
      },
    });
  };

  const handleAcknowledge = () => {
    router.post(
      `/notice-board/notices/${notice.id}/acknowledge`,
      {},
      { preserveScroll: true }
    );
  };

  const handleDeleteComment = (commentId: number) => {
    if (confirm('Delete this comment?')) {
      router.delete(`/notice-board/notices/${notice.id}/comments/${commentId}`, {
        preserveScroll: true,
      });
    }
  };

  const priorityBadge = (priority: string) => {
    switch (priority) {
      case 'critical':
        return <Badge variant="rose">Critical Priority</Badge>;
      case 'urgent':
        return <Badge variant="amber">Urgent Priority</Badge>;
      default:
        return <Badge variant="neutral">Normal Priority</Badge>;
    }
  };

  return (
    <AppShell title={notice.title}>
      <Head title={notice.title} />

      <div className="max-w-4xl mx-auto space-y-6 pb-16">
        {/* Navigation & Actions */}
        <div className="flex items-center justify-between gap-4">
          <Link href="/notice-board/board">
            <Button variant="outline" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
              Back to Board
            </Button>
          </Link>

          {canManage && (
            <Link href="/notice-board/notices">
              <Button variant="outline" size="sm">
                Manage All Notices
              </Button>
            </Link>
          )}
        </div>

        {/* Main Notice Card */}
        <Card level={1} className="space-y-6">
          {/* Header */}
          <div className="space-y-3 border-b border-[var(--border-subtle)] pb-5">
            <div className="flex flex-wrap items-center gap-2">
              {priorityBadge(notice.priority)}
              {notice.is_pinned && (
                <Badge variant="amber">
                  <Pin className="w-3 h-3 mr-1 inline" /> Pinned
                </Badge>
              )}
              {notice.status === 'draft' && <Badge variant="neutral">Draft</Badge>}
              {notice.status === 'deactivated' && <Badge variant="rose">Deactivated</Badge>}
            </div>

            <h1 className="text-2xl font-bold text-[var(--text-primary)] leading-snug">
              {notice.title}
            </h1>

            <div className="flex flex-wrap items-center gap-4 text-xs text-[var(--text-tertiary)] pt-1">
              {notice.creator && (
                <span>Published by <strong className="text-[var(--text-secondary)]">{notice.creator.name}</strong></span>
              )}
              <span className="flex items-center gap-1">
                <Calendar className="w-3.5 h-3.5" />
                Active: {formatDate(notice.start_date)}
                {notice.expiry_date ? ` to ${formatDate(notice.expiry_date)}` : ' (No expiry)'}
              </span>
            </div>
          </div>

          {/* Description / Content */}
          <div className="text-[var(--text-primary)] text-sm leading-relaxed whitespace-pre-wrap">
            {notice.description || 'No additional content provided for this notice.'}
          </div>

          {/* Attachments if any */}
          {notice.attachments && notice.attachments.length > 0 && (
            <div className="space-y-2 pt-4 border-t border-[var(--border-subtle)]">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                Attached Files
              </h4>
              <div className="flex flex-wrap gap-2">
                {notice.attachments.map((file, idx) => (
                  <a
                    key={idx}
                    href={file}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-medium)] text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--border-strong)] transition-colors"
                  >
                    <Download className="w-3.5 h-3.5" />
                    <span>Attachment #{idx + 1}</span>
                  </a>
                ))}
              </div>
            </div>
          )}

          {/* Acknowledgment Callout */}
          {notice.require_acknowledgment && (
            <div className="p-4 rounded-2xl border border-[var(--border-medium)] bg-[var(--surface-2)] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div className="space-y-0.5">
                <div className="flex items-center gap-2 text-sm font-semibold text-[var(--text-primary)]">
                  {userAcknowledged ? (
                    <>
                      <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                      <span>Notice Acknowledged</span>
                    </>
                  ) : (
                    <>
                      <AlertCircle className="w-4 h-4 text-amber-400" />
                      <span>Acknowledgment Required</span>
                    </>
                  )}
                </div>
                <p className="text-xs text-[var(--text-secondary)]">
                  {userAcknowledged && userRead?.acknowledged_at
                    ? `You acknowledged this notice on ${formatDateTime(userRead.acknowledged_at)}.`
                    : 'Please confirm that you have read and understood this official announcement.'}
                </p>
              </div>

              {!userAcknowledged && (
                <Button
                  variant="neutral"
                  onClick={handleAcknowledge}
                  icon={<CheckCircle2 className="w-4 h-4 text-emerald-400" />}
                >
                  Acknowledge Notice
                </Button>
              )}
            </div>
          )}

          {/* Manager stats section */}
          {canManage && readStats && (
            <div className="pt-4 border-t border-[var(--border-subtle)] space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4 text-xs text-[var(--text-secondary)]">
                  <span className="flex items-center gap-1 font-medium">
                    <Eye className="w-4 h-4 text-[var(--text-tertiary)]" />
                    Reads: <strong className="text-[var(--text-primary)]">{readStats.read_count}</strong>
                  </span>
                  {notice.require_acknowledgment && (
                    <span className="flex items-center gap-1 font-medium">
                      <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                      Acknowledged: <strong className="text-[var(--text-primary)]">{readStats.acknowledged_count}</strong>
                    </span>
                  )}
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setStatsOpen(!statsOpen)}
                  icon={<Users className="w-3.5 h-3.5" />}
                >
                  {statsOpen ? 'Hide Read Records' : 'View Read Records'}
                </Button>
              </div>

              {statsOpen && (
                <div className="max-h-48 overflow-y-auto rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-1)] p-3 divide-y divide-[var(--border-subtle)]">
                  {readStats.reads.length === 0 ? (
                    <div className="text-xs text-[var(--text-tertiary)] py-2 text-center">
                      No read activity recorded yet.
                    </div>
                  ) : (
                    readStats.reads.map((r) => (
                      <div key={r.id} className="py-2 flex items-center justify-between text-xs">
                        <span className="font-medium text-[var(--text-primary)]">
                          {r.user?.name || `User #${r.user_id}`}
                        </span>
                        <div className="flex items-center gap-3 text-[var(--text-tertiary)]">
                          {r.read_at && <span>Read: {formatDate(r.read_at)}</span>}
                          {r.acknowledged_at && (
                            <span className="text-emerald-400 flex items-center gap-1">
                              <CheckCircle2 className="w-3 h-3" /> Ack: {formatDate(r.acknowledged_at)}
                            </span>
                          )}
                        </div>
                      </div>
                    ))
                  )}
                </div>
              )}
            </div>
          )}
        </Card>

        {/* Comments Section */}
        {notice.allow_comments && (
          <Card level={1} className="space-y-6">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-semibold text-[var(--text-primary)] flex items-center gap-2">
                <MessageSquare className="w-4 h-4 text-[var(--text-secondary)]" />
                Discussion & Comments ({comments.length})
              </h3>
            </div>

            {/* Post comment form */}
            <form onSubmit={handlePostComment} className="space-y-3">
              <div className="relative">
                <textarea
                  rows={3}
                  required
                  value={commentForm.data.comment}
                  onChange={(e) => commentForm.setData('comment', e.target.value)}
                  placeholder={replyToId ? 'Write a reply...' : 'Share your feedback or questions...'}
                  className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none focus:border-[var(--border-strong)]"
                />
              </div>
              <div className="flex items-center justify-between">
                {replyToId ? (
                  <span className="text-xs text-[var(--text-secondary)] flex items-center gap-1">
                    <CornerDownRight className="w-3 h-3" /> Replying to comment #{replyToId}
                    <button
                      type="button"
                      onClick={() => {
                        setReplyToId(null);
                        commentForm.setData('parent_id', null);
                      }}
                      className="ml-2 text-rose-400 hover:underline"
                    >
                      Cancel
                    </button>
                  </span>
                ) : (
                  <span />
                )}
                <Button
                  type="submit"
                  variant="neutral"
                  size="sm"
                  loading={commentForm.processing}
                  icon={<Send className="w-3.5 h-3.5" />}
                >
                  {replyToId ? 'Post Reply' : 'Post Comment'}
                </Button>
              </div>
            </form>

            {/* Comments list */}
            <div className="space-y-4 pt-2 divide-y divide-[var(--border-subtle)]">
              {comments.length === 0 ? (
                <p className="text-xs text-[var(--text-tertiary)] text-center py-4">
                  No comments yet. Start the conversation above.
                </p>
              ) : (
                comments.map((comment) => (
                  <div key={comment.id} className="pt-4 space-y-3">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <span className="text-xs font-semibold text-[var(--text-primary)]">
                          {comment.user?.name || 'Member'}
                        </span>
                        <span className="text-[11px] text-[var(--text-tertiary)] ml-2">
                          {formatDateTime(comment.created_at)}
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => {
                            setReplyToId(comment.id);
                            commentForm.setData('parent_id', comment.id);
                          }}
                          className="text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
                        >
                          Reply
                        </button>
                        {(canManage || (currentUserId && comment.user_id === currentUserId)) && (
                          <button
                            onClick={() => handleDeleteComment(comment.id)}
                            className="text-[var(--text-tertiary)] hover:text-rose-400 transition-colors"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        )}
                      </div>
                    </div>

                    <p className="text-xs text-[var(--text-secondary)] leading-relaxed">
                      {comment.comment}
                    </p>

                    {/* Replies */}
                    {comment.replies && comment.replies.length > 0 && (
                      <div className="ml-6 space-y-3 pl-3 border-l border-[var(--border-subtle)] pt-1">
                        {comment.replies.map((reply) => (
                          <div key={reply.id} className="space-y-1">
                            <div className="flex items-center justify-between">
                              <div>
                                <span className="text-xs font-medium text-[var(--text-primary)]">
                                  {reply.user?.name || 'Member'}
                                </span>
                                <span className="text-[10px] text-[var(--text-tertiary)] ml-2">
                                  {formatDateTime(reply.created_at)}
                                </span>
                              </div>
                              {canManage && (
                                <button
                                  onClick={() => handleDeleteComment(reply.id)}
                                  className="text-[var(--text-tertiary)] hover:text-rose-400"
                                >
                                  <Trash2 className="w-3 h-3" />
                                </button>
                              )}
                            </div>
                            <p className="text-xs text-[var(--text-secondary)] leading-relaxed">
                              {reply.comment}
                            </p>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ))
              )}
            </div>
          </Card>
        )}
      </div>
    </AppShell>
  );
}
