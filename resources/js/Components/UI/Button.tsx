import React, { forwardRef } from 'react';
import { Loader2 } from 'lucide-react';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'intelligence' | 'outline';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: 'sm' | 'md' | 'lg';
  loading?: boolean;
  icon?: React.ReactNode;
  iconPosition?: 'left' | 'right';
  children?: React.ReactNode;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(({
  variant = 'primary',
  size = 'md',
  loading = false,
  icon,
  iconPosition = 'left',
  children,
  className = '',
  disabled,
  ...props
}, ref) => {
  const baseClasses =
    'inline-flex items-center justify-center font-medium rounded-xl select-none spring-transition focus:outline-none focus:ring-2 focus:ring-purple-500/50 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer';

  const sizeClasses = {
    sm: 'text-xs px-3 py-1.5 gap-1.5',
    md: 'text-sm px-4 py-2 gap-2',
    lg: 'text-base px-5 py-2.5 gap-2.5',
  };

  const variantClasses: Record<ButtonVariant, string> = {
    primary:
      'bg-gradient-to-tr from-purple-600 to-indigo-600 text-white shadow-lg hover:shadow-purple-500/25 hover:scale-[1.01] active:scale-[0.99]',
    secondary:
      'bg-[var(--surface-1)] hover:bg-[var(--surface-2)] text-[var(--text-primary)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)]',
    outline:
      'bg-transparent hover:bg-white/[0.05] text-[var(--text-primary)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)]',
    danger:
      'bg-rose-600 hover:bg-rose-500 text-white shadow-md shadow-rose-900/20 hover:scale-[1.01] active:scale-[0.99]',
    ghost:
      'bg-transparent hover:bg-white/[0.05] text-[var(--text-secondary)] hover:text-[var(--text-primary)]',
    intelligence:
      'bg-gradient-to-r from-purple-600 via-indigo-600 to-purple-700 text-white shadow-lg shadow-purple-600/30 hover:shadow-purple-500/50 hover:scale-[1.01] active:scale-[0.99] border border-purple-400/30',
  };

  return (
    <button
      ref={ref}
      className={`${baseClasses} ${sizeClasses[size]} ${variantClasses[variant]} ${className}`}
      disabled={disabled || loading}
      {...props}
    >
      {loading ? (
        <Loader2 className="w-4 h-4 animate-spin text-current" />
      ) : (
        <>
          {icon && iconPosition === 'left' && <span className="flex-shrink-0">{icon}</span>}
          {children && <span>{children}</span>}
          {icon && iconPosition === 'right' && <span className="flex-shrink-0">{icon}</span>}
        </>
      )}
    </button>
  );
});

Button.displayName = 'Button';
