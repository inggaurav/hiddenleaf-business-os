import React from 'react';
import { clsx } from 'clsx';

export interface MrFoxMarkProps extends React.SVGProps<SVGSVGElement> {
  size?: number;
  className?: string;
}

export const MrFoxMark: React.FC<MrFoxMarkProps> = ({
  size = 24,
  className,
  ...props
}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={clsx('flex-shrink-0 select-none', className)}
      aria-hidden="true"
      {...props}
    >
      <defs>
        <linearGradient id="hl-fox-gradient" x1="2" y1="2" x2="30" y2="30" gradientUnits="userSpaceOnUse">
          <stop stopColor="#F59E0B" />
          <stop offset="0.5" stopColor="#A855F7" />
          <stop offset="1" stopColor="#06B6D4" />
        </linearGradient>
        <linearGradient id="hl-fox-inner" x1="16" y1="8" x2="16" y2="26" gradientUnits="userSpaceOnUse">
          <stop stopColor="#FFFFFF" stopOpacity="0.9" />
          <stop offset="1" stopColor="#FFFFFF" stopOpacity="0.3" />
        </linearGradient>
      </defs>

      {/* Fox Neural Crown / Ears */}
      <path
        d="M6 8L11 16L6 24L16 28L26 24L21 16L26 8L16 12L6 8Z"
        fill="url(#hl-fox-gradient)"
        opacity="0.25"
      />
      <path
        d="M6 8L12 15L16 11L20 15L26 8L22 19L16 27L10 19L6 8Z"
        stroke="url(#hl-fox-gradient)"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />

      {/* Neural Core Node */}
      <circle cx="16" cy="17" r="3" fill="url(#hl-fox-inner)" />
      <path d="M12 15L16 17L20 15" stroke="#FFFFFF" strokeWidth="1.5" strokeLinecap="round" opacity="0.8" />
    </svg>
  );
};
