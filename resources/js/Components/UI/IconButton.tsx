import React from 'react';
import { clsx } from 'clsx';

export interface IconButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'intelligence';
  size?: 'sm' | 'md' | 'lg';
  label: string;
}

export const IconButton = React.forwardRef<HTMLButtonElement, IconButtonProps>(({
  children,
  className,
  variant = 'ghost',
  size = 'md',
  label,
  disabled = false,
  ...props
}, ref) => {
  const baseStyles = 'inline-flex items-center justify-center rounded-lg spring-transition focus-ring disabled:opacity-50 disabled:cursor-not-allowed select-none cursor-pointer active:scale-95';

  const sizeStyles = {
    sm: 'w-7 h-7 p-1 text-xs',
    md: 'w-9 h-9 p-2 text-sm',
    lg: 'w-11 h-11 p-2.5 text-base rounded-xl',
  };

  const variantStyles = {
    primary: 'bg-violet-600 hover:bg-violet-500 text-white border border-violet-500/30 shadow-sm',
    secondary: 'bg-white/[0.06] hover:bg-white/[0.12] text-gray-300 hover:text-white border border-white/10',
    ghost: 'bg-transparent hover:bg-white/[0.08] text-gray-400 hover:text-white',
    danger: 'bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/20',
    intelligence: 'bg-purple-600/30 hover:bg-purple-600/50 text-purple-200 border border-purple-400/30',
  };

  return (
    <button
      ref={ref}
      disabled={disabled}
      aria-label={label}
      title={label}
      className={clsx(baseStyles, sizeStyles[size], variantStyles[variant], className)}
      {...props}
    >
      {children}
    </button>
  );
});

IconButton.displayName = 'IconButton';
