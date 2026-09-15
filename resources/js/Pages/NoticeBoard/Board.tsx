import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  Pin,
  AlertTriangle,
  CheckCircle2,
  Clock,
  MessageSquare,
  Eye,
  Calendar,
  Filter,
  Plus,
  ArrowRight,
  ShieldAlert,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Card } from '@/Components/UI/Card';
import { formatDate } from '@/lib/format';
import { NoticeItem } from './types';

interface BoardProps {
  notices: NoticeItem[];
  draftNotices: NoticeItem[];
  readNoticeIds: number[];
  acknowledgedNoticeIds: number[];
  canManage: boolean;
  filters: {
    priority?: string;
  };
}

export default function Board({
  notices,
  draftNotices,
  readNoticeIds,
  acknowledgedNoticeIds,
  canManage,
  filters,
}: BoardProps) {
  const [priorityFilter, setPriorityFilter] = useState<string>(filters.priority || 'all');
  const [acknowledgingId, setAcknowledgingId] = useState<number | null>(null);

  const readSet = useMemo(() => new Set(readNoticeIds), [readNoticeIds]);
  const ackSet = useMemo(() => new Set(acknowledgedNoticeIds), [acknowledgedNoticeIds]);

  const filteredNotices = useMemo(() => {
    if (priorityFilter === 'all') return notices;
    return notices.filter((n) => n.priority === priorityFilter);
  }, [notices, priorityFilter]);

  const criticalPending = useMemo(() => {
    return notices.filter(
      (n) => n.priority === 'critical' && (!readSet.has(n.id) || (n.require_acknowledgment && !ackSet.has(n.id)))
    );
  }, [notices, readSet, ackSet]);

  const pinnedNotices = useMemo(() => filteredNotices.filter((n) => n.is_pinned), [filteredNotices]);
  const regularNotices = useMemo(() => filteredNotices.filter((n) => !n.is_pinned), [filteredNotices]);

  const handleAcknowledge = (e: React.MouseEvent, noticeId: number) => {
    e.stopPropagation();
    e.preventDefault();
    setAcknowledgingId(noticeId);
    router.post(
      `/notice-board/notices/${noticeId}/acknowledge`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setAcknowledgingId(null),
      }
    );
  };

  const priorityBadge = (priority: string) => {
    switch (priority) {
      case 'critical':
        return <Badge variant="rose">Critical</Badge>;
      case 'urgent':
        return <Badge variant="amber">Urgent</Badge>;
      default:
        return <Badge variant="neutral">Normal</Badge>;
    }
  };

  return (
    <AppShell title="Notice Board">
      <Head title="Notice Board" />

      <div className="space-y-6 pb-12">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Notice Board</h1>
            <p className="text-sm text-[var(--text-secondary)] mt-0.5">
              Official announcements, company updates, and mandatory acknowledgments.
            </p>
          </div>
          {canManage && (
            <div className="flex items-center gap-2">
              <Link href="/notice-board/notices">
                <Button variant="neutral" icon={<Plus className="w-4 h-4" />}>
                  Manage Notices
                </Button>
              </Link>
            </div>
          )}
        </div>

        {/* Critical notices banner if any unacknowledged */}
        {criticalPending.length > 0 && (
          <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 sm:p-5 space-y-3">
            <div className="flex items-start gap-3">
              <ShieldAlert className="w-5 h-5 text-rose-400 mt-0.5 flex-shrink-0" />
              <div className="flex-1">
                <h3 className="text-sm font-semibold text-rose-300">
                  Critical Action Required ({criticalPending.length})
                </h3>
                <p className="text-xs text-rose-200/80 mt-0.5">
                  You have high-priority critical notices requiring immediate attention or acknowledgment.
                </p>
              </div>
            </div>
            <div className="space-y-2 pt-1">
              {criticalPending.map((item) => (
                <div
                  key={item.id}
                  className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-black/20 border border-rose-500/20"
                >
                  <div className="space-y-0.5">
                    <span className="text-sm font-medium text-[var(--text-primary)]">{item.title}</span>
                    <p className="text-xs text-[var(--text-tertiary)]">
                      Published {formatDate(item.start_date)}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    {item.require_acknowledgment && !ackSet.has(item.id) && (
                      <Button
                        variant="neutral"
                        size="sm"
                        loading={acknowledgingId === item.id}
                        onClick={(e) => handleAcknowledge(e, item.id)}
                        icon={<CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />}
                      >
                        Acknowledge
                      </Button>
                    )}
                    <Link href={`/notice-board/notices/${item.id}`}>
                      <Button variant="outline" size="sm">
                        View Notice
                      </Button>
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Filter Bar */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1">
          <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1 mr-2">
            <Filter className="w-3.5 h-3.5" /> Priority:
          </span>
          {['all', 'critical', 'urgent', 'normal'].map((filter) => (
            <button
              key={filter}
              onClick={() => setPriorityFilter(filter)}
              className={`px-3 py-1.5 rounded-xl text-xs font-medium capitalize spring-transition cursor-pointer ${
                priorityFilter === filter
                  ? 'bg-[var(--surface-3)] text-[var(--text-primary)] border border-[var(--border-strong)]'
                  : 'bg-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-[var(--border-subtle)]'
              }`}
            >
              {filter}
            </button>
          ))}
        </div>

        {/* Pinned Notices Section */}
        {pinnedNotices.length > 0 && (
          <div className="space-y-3">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              <Pin className="w-3.5 h-3.5 text-amber-400" />
              Pinned Announcements
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {pinnedNotices.map((notice) => (
                <NoticeCard
                  key={notice.id}
                  notice={notice}
                  isRead={readSet.has(notice.id)}
                  isAcknowledged={ackSet.has(notice.id)}
                  onAcknowledge={(e) => handleAcknowledge(e, notice.id)}
                  isAcknowledging={acknowledgingId === notice.id}
                  priorityBadge={priorityBadge(notice.priority)}
                />
              ))}
            </div>
          </div>
        )}

        {/* General Announcements */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <div className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              {pinnedNotices.length > 0 ? 'All Announcements' : 'Announcements'} ({filteredNotices.length})
            </div>
          </div>

          {regularNotices.length === 0 && pinnedNotices.length === 0 ? (
            <Card level={1} className="text-center py-12">
              <div className="max-w-md mx-auto space-y-3">
                <div className="w-12 h-12 rounded-2xl bg-[var(--surface-2)] flex items-center justify-center mx-auto text-[var(--text-tertiary)]">
                  <Calendar className="w-6 h-6" />
                </div>
                <h3 className="text-sm font-semibold text-[var(--text-primary)]">No notices found</h3>
                <p className="text-xs text-[var(--text-secondary)]">
                  There are currently no active announcements matching your selection.
                </p>
              </div>
            </Card>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {regularNotices.map((notice) => (
                <NoticeCard
                  key={notice.id}
                  notice={notice}
                  isRead={readSet.has(notice.id)}
                  isAcknowledged={ackSet.has(notice.id)}
                  onAcknowledge={(e) => handleAcknowledge(e, notice.id)}
                  isAcknowledging={acknowledgingId === notice.id}
                  priorityBadge={priorityBadge(notice.priority)}
                />
              ))}
            </div>
          )}
        </div>

        {/* Draft notices for managers */}
        {canManage && draftNotices.length > 0 && (
          <div className="space-y-3 pt-6 border-t border-[var(--border-subtle)]">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
              <Clock className="w-3.5 h-3.5" />
              Unpublished Drafts ({draftNotices.length})
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {draftNotices.map((notice) => (
                <NoticeCard
                  key={notice.id}
                  notice={notice}
                  isRead={false}
                  isAcknowledged={false}
                  onAcknowledge={() => {}}
                  isAcknowledging={false}
                  priorityBadge={priorityBadge(notice.priority)}
                  isDraft
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </AppShell>
  );
}

interface NoticeCardProps {
  notice: NoticeItem;
  isRead: boolean;
  isAcknowledged: boolean;
  onAcknowledge: (e: React.MouseEvent) => void;
  isAcknowledging: boolean;
  priorityBadge: React.ReactNode;
  isDraft?: boolean;
}

function NoticeCard({
  notice,
  isRead,
  isAcknowledged,
  onAcknowledge,
  isAcknowledging,
  priorityBadge,
  isDraft = false,
}: NoticeCardProps) {
  return (
    <Card
      level={notice.is_pinned ? 2 : 1}
      className={`relative flex flex-col justify-between hover:border-[var(--border-strong)] transition-all group ${
        notice.priority === 'critical' ? 'border-rose-500/20' : ''
      }`}
    >
      <div className="space-y-3">
        {/* Top meta */}
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            {priorityBadge}
            {notice.is_pinned && (
              <Badge variant="amber" size="sm">
                <Pin className="w-3 h-3 mr-1 inline" /> Pinned
              </Badge>
            )}
            {isDraft && <Badge variant="neutral" size="sm">Draft</Badge>}
            {!isDraft && !isRead && (
              <span className="flex items-center gap-1 text-[11px] text-amber-400 font-medium">
                <span className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />
                Unread
              </span>
            )}
          </div>
          <span className="text-[11px] text-[var(--text-tertiary)] flex items-center gap-1">
            <Calendar className="w-3 h-3" />
            {formatDate(notice.start_date)}
            {notice.expiry_date && ` - ${formatDate(notice.expiry_date)}`}
          </span>
        </div>

        {/* Title & Body preview */}
        <div>
          <Link
            href={`/notice-board/notices/${notice.id}`}
            className="text-base font-semibold text-[var(--text-primary)] hover:text-white transition-colors group-hover:underline line-clamp-1"
          >
            {notice.title}
          </Link>
          {notice.description && (
            <p className="text-xs text-[var(--text-secondary)] mt-1.5 line-clamp-2 leading-relaxed">
              {notice.description.replace(/<[^>]+>/g, '')}
            </p>
          )}
        </div>
      </div>

      {/* Footer */}
      <div className="mt-4 pt-3 border-t border-[var(--border-subtle)] flex items-center justify-between gap-2">
        <div className="flex items-center gap-3 text-xs text-[var(--text-tertiary)]">
          {notice.creator?.name && (
            <span>By {notice.creator.name}</span>
          )}
          {notice.allow_comments && (
            <span className="flex items-center gap-1">
              <MessageSquare className="w-3 h-3" />
              {notice.comments_count ?? 0}
            </span>
          )}
          {notice.reads_count !== undefined && (
            <span className="flex items-center gap-1">
              <Eye className="w-3 h-3" />
              {notice.reads_count}
            </span>
          )}
        </div>

        <div className="flex items-center gap-2">
          {notice.require_acknowledgment && !isDraft && (
            isAcknowledged ? (
              <span className="text-xs text-emerald-400 flex items-center gap-1">
                <CheckCircle2 className="w-3.5 h-3.5" /> Acknowledged
              </span>
            ) : (
              <Button
                variant="neutral"
                size="sm"
                loading={isAcknowledging}
                onClick={onAcknowledge}
                icon={<CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />}
              >
                Acknowledge
              </Button>
            )
          )}
          <Link href={`/notice-board/notices/${notice.id}`}>
            <Button variant="outline" size="sm" icon={<ArrowRight className="w-3.5 h-3.5" />} iconPosition="right">
              Read
            </Button>
          </Link>
        </div>
      </div>
    </Card>
  );
}
