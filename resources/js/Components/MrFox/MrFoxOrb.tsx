import React from 'react';
import { MrFoxMark } from './MrFoxMark';

interface MrFoxOrbProps {
  size?: 'sm' | 'md' | 'lg';
  active?: boolean;
}

export const MrFoxOrb: React.FC<MrFoxOrbProps> = ({
  size = 'md',
  active = false,
}) => {
  const sizeMap = {
    sm: 'w-10 h-10',
    md: 'w-12 h-12',
    lg: 'w-16 h-16',
  };

  const iconMap = {
    sm: 20,
    md: 26,
    lg: 34,
  };

  return (
    <div className={`relative ${sizeMap[size]} flex items-center justify-center`}>
      {/* Background glow layers */}
      <div className="absolute inset-0 rounded-full bg-gradient-to-tr from-amber-500/30 via-purple-600/40 to-cyan-500/30 blur-md animate-pulse" />
      
      {/* Glass Orb Shell */}
      <div className="relative w-full h-full rounded-full bg-purple-950/60 border border-purple-400/40 shadow-xl backdrop-blur-md flex items-center justify-center">
        <MrFoxMark size={iconMap[size]} />
      </div>

      {active && (
        <span className="absolute top-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-black animate-ping" />
      )}
    </div>
  );
};
