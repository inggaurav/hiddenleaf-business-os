import React, { forwardRef } from 'react';
import { Loader2 } from 'lucide-react';

export type ButtonVariant =
  | 'neutral'      // default — replaces old "primary" for everyday actions
  | 'outline'      // secondary / cancel
  | 'ghost'        // table row actions, icon-adjacent
  | 'danger'       // destructive only
  | 'intelligence' // MrFox AI actions ONLY — do not use for anything else
  | 'primary'      // @deprecated — kept for backwards compat, maps to neutral
  | 'secondary';   // alias for outline

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: 'sm' | 'md' | 'lg';
  loading?: boolean;
  icon?: React.ReactNode;
  iconPosition?: 'left' | 'right';
  children?: React.ReactNode;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>((
  {
    variant = 'neutral',
    size = 'md',
    loading = false,
    icon,
    iconPosition = 'left',
    children,
    className = '',
    disabled,
    ...props
  },
  ref,
) => {
  const base =
    'inline-flex items-center justify-center font-medium rounded-xl select-none spring-transition focus:outline-none focus-ring disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer';

  const sizes = {
    sm: 'text-xs px-3 py-1.5 gap-1.5 h-7',
    md: 'text-[13px] px-4 py-2 gap-2 h-9',
    lg: 'text-sm px-5 py-2.5 gap-2 h-11',
  };

  const variants: Record<ButtonVariant, string> = {
    // Solid neutral — replaces old purple "primary"
    neutral:
      'bg-[var(--surface-3)] hover:bg-[var(--border-medium)] text-[var(--text-primary)] border border-[var(--border-medium)] hover:border-[var(--border-strong)] active:scale-[0.98]',
    // Alias for old "primary" — same as neutral, removes purple
    primary:
      'bg-[var(--surface-3)] hover:bg-[var(--border-medium)] text-[var(--text-primary)] border border-[var(--border-medium)] hover:border-[var(--border-strong)] active:scale-[0.98]',
    // Transparent with border — secondary actions, cancel
    outline:
      'bg-transparent hover:bg-white/[0.04] text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] active:scale-[0.98]',
    secondary:
      'bg-transparent hover:bg-white/[0.04] text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] active:scale-[0.98]',
    // No background — table row actions
    ghost:
      'bg-transparent hover:bg-white/[0.05] text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-transparent active:scale-[0.98]',
    // Destructive — terminate, reject, delete
    danger:
      'bg-rose-600 hover:bg-rose-500 text-white border border-rose-500/30 shadow-sm active:scale-[0.98]',
    // MrFox AI ONLY — purple gradient
    intelligence:
      'bg-gradient-to-r from-[var(--brand-primary)] to-[var(--fox-purple)] text-white border border-[var(--brand-primary)]/30 shadow-md active:scale-[0.98]',
  };

  return (
    <button
      ref={ref}
      className={`${base} ${sizes[size]} ${variants[variant]} ${className}`}
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
