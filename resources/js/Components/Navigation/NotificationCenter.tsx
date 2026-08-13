import React, { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { Bell, Check, Trash2, Clock, AlertCircle, Info, Inbox } from 'lucide-react';
import { IconButton } from '../UI/IconButton';

interface NotificationItem {
  id: string;
  title: string;
  message: string;
  read_at: string | null;
  created_at: string;
  type?: 'info' | 'warning' | 'security';
}

interface NotificationCenterProps {
  initialNotifications?: NotificationItem[];
}

export const NotificationCenter: React.FC<NotificationCenterProps> = ({
  initialNotifications,
}) => {
  const { auth } = usePage<any>().props;
  const sharedNotifications: NotificationItem[] = auth?.notifications || initialNotifications || [];

  const [isOpen, setIsOpen] = useState(false);
  const [notifications, setNotifications] = useState<NotificationItem[]>(sharedNotifications);
  const [status, setStatus] = useState<'idle' | 'loading' | 'error'>('idle');

  const containerRef = useRef<HTMLDivElement>(null);
  const unreadCount = notifications.filter((n) => !n.read_at).length;

  useEffect(() => {
    if (auth?.notifications) {
      setNotifications(auth.notifications);
    }
  }, [auth?.notifications]);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const markAsRead = (id: string) => {
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
    );
  };

  const markAllAsRead = () => {
    setNotifications((prev) =>
      prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
    );
  };

  return (
    <div className="relative inline-block text-left" ref={containerRef}>
      <div className="relative">
        <IconButton
          label={`Notifications (${unreadCount} unread)`}
          variant="secondary"
          size="sm"
          onClick={() => setIsOpen(!isOpen)}
          aria-expanded={isOpen}
          aria-haspopup="dialog"
        >
          <Bell className="w-4 h-4 text-[var(--text-secondary)]" />
        </IconButton>

        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 w-2 h-2 bg-purple-500 rounded-full animate-pulse" />
        )}
      </div>

      {isOpen && (
        <div
          role="dialog"
          aria-label="Notification Center"
          className="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-[var(--surface-1)] border border-[var(--border-medium)] shadow-2xl overflow-hidden z-50 spring-transition"
        >
          <div className="p-4 border-b border-[var(--border-subtle)] flex items-center justify-between bg-[var(--surface-2)]">
            <div className="flex items-center gap-2">
              <h3 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">
                Notifications
              </h3>
              {unreadCount > 0 && (
                <span className="px-1.5 py-0.5 rounded-full bg-purple-600/20 text-purple-300 text-[10px] font-bold border border-purple-500/30">
                  {unreadCount} new
                </span>
              )}
            </div>

            {unreadCount > 0 && (
              <button
                type="button"
                onClick={markAllAsRead}
                className="text-[11px] text-purple-400 hover:text-purple-300 font-medium cursor-pointer"
              >
                Mark all read
              </button>
            )}
          </div>

          <div className="max-h-80 overflow-y-auto divide-y divide-[var(--border-subtle)]">
            {status === 'loading' ? (
              <div className="py-12 text-center text-xs text-[var(--text-tertiary)]">
                Loading notifications...
              </div>
            ) : status === 'error' ? (
              <div className="py-12 text-center text-xs text-rose-400">
                Failed to load notifications.
              </div>
            ) : notifications.length > 0 ? (
              notifications.map((n) => {
                const isUnread = !n.read_at;

                return (
                  <div
                    key={n.id}
                    className={`p-3.5 flex items-start gap-3 text-xs spring-transition ${
                      isUnread ? 'bg-purple-950/20' : 'hover:bg-white/[0.02]'
                    }`}
                  >
                    <div className="mt-0.5">
                      {n.type === 'warning' ? (
                        <AlertCircle className="w-4 h-4 text-amber-400" />
                      ) : (
                        <Info className="w-4 h-4 text-purple-400" />
                      )}
                    </div>

                    <div className="flex-1 space-y-1 min-w-0">
                      <div className="flex items-center justify-between gap-2">
                        <span className={`font-semibold truncate ${isUnread ? 'text-[var(--text-primary)]' : 'text-[var(--text-secondary)]'}`}>
                          {n.title}
                        </span>
                        <span className="text-[10px] text-[var(--text-tertiary)] whitespace-nowrap">
                          {n.created_at}
                        </span>
                      </div>
                      <p className="text-[11px] text-[var(--text-secondary)] leading-relaxed">{n.message}</p>
                    </div>

                    {isUnread && (
                      <button
                        type="button"
                        onClick={() => markAsRead(n.id)}
                        className="p-1 text-[var(--text-tertiary)] hover:text-purple-300 spring-transition"
                        title="Mark as read"
                      >
                        <Check className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>
                );
              })
            ) : (
              <div className="py-12 text-center space-y-2">
                <Inbox className="w-8 h-8 text-[var(--text-tertiary)] mx-auto opacity-50" />
                <p className="text-xs font-semibold text-[var(--text-primary)]">No notifications</p>
                <p className="text-[11px] text-[var(--text-tertiary)]">You have no unread alerts at this time.</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
