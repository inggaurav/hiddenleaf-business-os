import React from 'react';
import { clsx } from 'clsx';
import { MrFoxMark } from './MrFoxMark';

export type MrFoxState = 
  | 'disconnected'
  | 'idle' 
  | 'listening' 
  | 'thinking' 
  | 'responding' 
  | 'approval_required' 
  | 'error';

export interface MrFoxOrbProps {
  state?: MrFoxState;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
  onClick?: () => void;
}

export const MrFoxOrb: React.FC<MrFoxOrbProps> = ({
  state = 'idle',
  size = 'md',
  className,
  onClick,
}) => {
  const sizeStyles = {
    sm: 'w-8 h-8 p-1',
    md: 'w-10 h-10 p-1.5',
    lg: 'w-14 h-14 p-2.5',
  };

  const markSizes = {
    sm: 18,
    md: 22,
    lg: 32,
  };

  const stateOrbStyles: Record<MrFoxState, string> = {
    disconnected: 'bg-white/[0.06] border-white/15 text-gray-400',
    idle: 'bg-gradient-to-tr from-amber-500/20 via-purple-600/30 to-cyan-500/20 border-purple-500/30 shadow-md shadow-purple-950/40 text-white',
    listening: 'bg-gradient-to-tr from-cyan-500/40 via-purple-600/40 to-blue-500/40 border-cyan-400/60 shadow-cyan-950/50 text-cyan-200 animate-fox-pulse',
    thinking: 'bg-gradient-to-tr from-purple-600/50 via-indigo-600/50 to-purple-900/50 border-purple-400 shadow-purple-950/60 text-purple-200 animate-pulse',
    responding: 'bg-gradient-to-tr from-violet-500/40 via-cyan-500/40 to-indigo-500/40 border-violet-400 shadow-violet-950/50 text-white',
    approval_required: 'bg-gradient-to-tr from-amber-500/50 via-rose-500/40 to-amber-600/50 border-amber-400/80 shadow-amber-950/60 text-amber-200',
    error: 'bg-rose-500/20 border-rose-500/50 text-rose-300',
  };

  return (
    <div
      onClick={onClick}
      className={clsx(
        'relative rounded-2xl border flex items-center justify-center spring-transition',
        sizeStyles[size],
        stateOrbStyles[state],
        onClick && 'cursor-pointer hover:scale-105 active:scale-95',
        className
      )}
    >
      <MrFoxMark size={markSizes[size]} />
    </div>
  );
};
