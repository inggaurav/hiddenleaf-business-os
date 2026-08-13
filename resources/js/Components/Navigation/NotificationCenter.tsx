import React, { useState, useEffect, useRef } from 'react';
import { Bell, CheckCheck, Inbox, ShieldCheck, X } from 'lucide-react';
import { Badge } from '../UI/Badge';
import { Button } from '../UI/Button';

export interface NotificationItem {
  id: string | number;
  title: string;
  message: string;
  read: boolean;
  time: string;
}

interface NotificationCenterProps {
  initialNotifications?: NotificationItem[];
}

export const NotificationCenter: React.FC<NotificationCenterProps> = ({
  initialNotifications = [],
}) => {
  const [open, setOpen] = useState(false);
  const [notifications, setNotifications] = useState<NotificationItem[]>(initialNotifications);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const unreadCount = notifications.filter((n) => !n.read).length;

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleMarkAllRead = () => {
    setNotifications((prev) => prev.map((n) => ({ ...n, read: true })));
  };

  const handleMarkRead = (id: string | number) => {
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, read: true } : n))
    );
  };

  return (
    <div ref={dropdownRef} className="relative inline-block text-left">
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        aria-expanded={open}
        aria-label={`Notifications (${unreadCount} unread)`}
        className="p-2 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-gray-300 hover:text-white spring-transition cursor-pointer relative"
      >
        <Bell className="w-4 h-4" />
        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-violet-600 text-white text-[10px] font-bold flex items-center justify-center shadow-md">
            {unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-80 sm:w-96 glass-dropdown rounded-xl border border-white/15 shadow-2xl p-3 z-50 animate-in zoom-in-95 duration-150 space-y-3">
          <div className="flex items-center justify-between pb-2 border-b border-white/10">
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-white tracking-tight uppercase">
                Activity & Alerts
              </span>
              {unreadCount > 0 && (
                <Badge variant="purple" size="sm">
                  {unreadCount} new
                </Badge>
              )}
            </div>

            {unreadCount > 0 && (
              <button
                type="button"
                onClick={handleMarkAllRead}
                className="text-[11px] text-purple-300 hover:text-white flex items-center gap-1 font-medium cursor-pointer"
              >
                <CheckCheck className="w-3.5 h-3.5" /> Mark all read
              </button>
            )}
          </div>

          <div className="max-h-72 overflow-y-auto space-y-2 pr-1">
            {notifications.length > 0 ? (
              notifications.map((item) => (
                <div
                  key={item.id}
                  onClick={() => handleMarkRead(item.id)}
                  className={`
                    p-3 rounded-lg border text-xs cursor-pointer spring-transition select-none
                    ${item.read ? 'bg-white/[0.02] border-white/5 opacity-70' : 'bg-purple-950/20 border-purple-500/30'}
                  `}
                >
                  <div className="flex items-center justify-between font-semibold text-white">
                    <span>{item.title}</span>
                    <span className="text-[10px] text-gray-500 font-normal">{item.time}</span>
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1 leading-relaxed">{item.message}</p>
                </div>
              ))
            ) : (
              <div className="py-8 text-center space-y-2">
                <Inbox className="w-8 h-8 text-gray-600 mx-auto" />
                <p className="text-xs font-semibold text-white">No active alerts</p>
                <p className="text-[11px] text-gray-400">All tenant events and orders are caught up.</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
