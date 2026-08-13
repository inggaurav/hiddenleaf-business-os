import React from 'react';

interface MrFoxMarkProps {
  size?: number;
  className?: string;
}

export const MrFoxMark: React.FC<MrFoxMarkProps> = ({ size = 24, className = '' }) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-label="Mr Fox Logo"
    >
      <path
        d="M6 8L12 18L16 12L20 18L26 8L22 24H10L6 8Z"
        fill="url(#fox_gradient_1)"
      />
      <circle cx="12" cy="14" r="1.5" fill="#FFFFFF" />
      <circle cx="20" cy="14" r="1.5" fill="#FFFFFF" />
      <path
        d="M14 20L16 22L18 20"
        stroke="#FFFFFF"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <defs>
        <linearGradient id="fox_gradient_1" x1="6" y1="8" x2="26" y2="24" gradientUnits="userSpaceOnUse">
          <stop stopColor="#F59E0B" />
          <stop offset="0.5" stopColor="#8B5CF6" />
          <stop offset="1" stopColor="#06B6D4" />
        </linearGradient>
      </defs>
    </svg>
  );
};
