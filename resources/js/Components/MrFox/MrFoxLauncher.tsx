import React from 'react';
import { MrFoxMark } from './MrFoxMark';
import { Badge } from '../UI/Badge';

interface MrFoxLauncherProps {
  onClick: () => void;
  unreadCount?: number;
}

export const MrFoxLauncher: React.FC<MrFoxLauncherProps> = ({
  onClick,
  unreadCount = 0,
}) => {
  return (
    <button
      type="button"
      onClick={onClick}
      className="fixed bottom-6 right-6 z-40 group flex items-center gap-3 p-2 pr-4 rounded-full glass-2 border border-purple-500/30 hover:border-purple-400 shadow-2xl hover:shadow-purple-900/40 spring-transition select-none bg-[var(--surface-1)] cursor-pointer"
      aria-label="Open Mr Fox Intelligence Assistant"
    >
      <div className="relative">
        <div className="w-10 h-10 rounded-full bg-purple-900/40 border border-purple-500/30 flex items-center justify-center shadow-lg group-hover:scale-105 spring-transition">
          <MrFoxMark size={24} />
        </div>

        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center animate-bounce">
            {unreadCount}
          </span>
        )}
      </div>

      <div className="flex flex-col text-left">
        <div className="flex items-center gap-1.5">
          <span className="text-xs font-bold text-[var(--text-primary)] group-hover:text-purple-300 spring-transition">
            Mr Fox
          </span>
          <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-[var(--surface-2)] text-[var(--text-tertiary)] border border-[var(--border-subtle)] font-medium">
            Ready
          </span>
        </div>
        <span className="text-[10px] text-[var(--text-secondary)]">AI Assistant</span>
      </div>

      <kbd className="hidden sm:inline-block text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-2)] border border-[var(--border-subtle)] text-[var(--text-tertiary)] ml-1">
        ⌘J
      </kbd>
    </button>
  );
};
