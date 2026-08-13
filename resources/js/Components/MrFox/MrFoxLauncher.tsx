import React from 'react';
import { Sparkles, Bot } from 'lucide-react';
import { clsx } from 'clsx';

export type MrFoxState = 'idle' | 'listening' | 'thinking' | 'responding' | 'action_ready' | 'approval_required' | 'error';

interface MrFoxLauncherProps {
  state?: MrFoxState;
  onClick: () => void;
  unreadInsightsCount?: number;
}

export const MrFoxLauncher: React.FC<MrFoxLauncherProps> = ({
  state = 'idle',
  onClick,
  unreadInsightsCount = 2,
}) => {
  const auraStyles: Record<MrFoxState, string> = {
    idle: 'from-amber-500/30 via-purple-600/30 to-indigo-600/30 border-amber-500/40 shadow-purple-950/40',
    listening: 'from-cyan-500/50 via-purple-600/50 to-blue-600/50 border-cyan-400 shadow-cyan-950/50',
    thinking: 'from-purple-600/60 via-indigo-600/60 to-purple-800/60 border-purple-400 shadow-purple-950/60 animate-pulse',
    responding: 'from-violet-500/50 via-indigo-500/50 to-cyan-500/50 border-violet-400 shadow-violet-950/50',
    action_ready: 'from-blue-500/60 via-emerald-500/50 to-violet-600/60 border-blue-400 shadow-blue-950/50',
    approval_required: 'from-amber-500/60 via-rose-500/50 to-amber-600/60 border-amber-400 shadow-amber-950/50 animate-bounce',
    error: 'from-rose-600/60 via-red-600/60 to-rose-800/60 border-rose-500 shadow-rose-950/50',
  };

  return (
    <button
      onClick={onClick}
      aria-label="Ask Mr Fox Intelligence"
      title="Ask Mr Fox (AI Business Intelligence)"
      className={clsx(
        'group fixed bottom-6 right-6 z-40 flex items-center gap-2.5 px-4 py-2.5 rounded-full glass-2 cursor-pointer spring-transition active:scale-95 shadow-xl border bg-gradient-to-r',
        auraStyles[state]
      )}
    >
      <div className="relative flex items-center justify-center">
        <div className="w-7 h-7 rounded-full bg-gradient-to-tr from-amber-500 to-purple-600 flex items-center justify-center text-white shadow-inner">
          <Sparkles className="w-4 h-4 animate-fox-pulse" />
        </div>
        {unreadInsightsCount > 0 && (
          <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-amber-500 text-black text-[10px] font-bold flex items-center justify-center shadow-md">
            {unreadInsightsCount}
          </span>
        )}
      </div>

      <div className="text-left hidden sm:block">
        <span className="text-xs font-bold text-white tracking-tight flex items-center gap-1.5">
          Ask Mr Fox <span className="text-[10px] font-normal text-purple-300">⌘J</span>
        </span>
        <span className="text-[10px] text-gray-300 block -mt-0.5">
          {state === 'idle' ? 'Intelligence Online' : state.replace('_', ' ')}
        </span>
      </div>
    </button>
  );
};
