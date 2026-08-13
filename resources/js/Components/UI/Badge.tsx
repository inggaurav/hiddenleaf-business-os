import React from 'react';

export type BadgeVariant = 'neutral' | 'purple' | 'success' | 'warning' | 'danger' | 'info' | 'cyan' | 'emerald' | 'amber' | 'rose' | 'indigo';

export interface BadgeProps {
  children?: React.ReactNode;
  variant?: BadgeVariant;
  size?: 'sm' | 'md' | 'lg';
  dot?: boolean;
  className?: string;
  label?: string;
  status?: string;
}

export const Badge: React.FC<BadgeProps> = ({
  children,
  variant = 'neutral',
  size = 'md',
  dot = false,
  className = '',
  label,
}) => {
  const sizeClasses = {
    sm: 'text-[10px] px-2 py-0.5 gap-1',
    md: 'text-xs px-2.5 py-1 gap-1.5',
    lg: 'text-sm px-3 py-1.5 gap-2',
  };

  const variantClasses: Record<BadgeVariant, string> = {
    neutral: 'bg-white/[0.06] text-[var(--text-secondary)] border border-[var(--border-subtle)]',
    purple: 'bg-purple-500/10 text-purple-400 border border-purple-500/20',
    success: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
    emerald: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
    warning: 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
    amber: 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
    danger: 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
    rose: 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
    info: 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20',
    indigo: 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20',
    cyan: 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20',
  };

  const dotClasses: Record<BadgeVariant, string> = {
    neutral: 'bg-gray-400',
    purple: 'bg-purple-400',
    success: 'bg-emerald-400',
    emerald: 'bg-emerald-400',
    warning: 'bg-amber-400',
    amber: 'bg-amber-400',
    danger: 'bg-rose-400',
    rose: 'bg-rose-400',
    info: 'bg-indigo-400',
    indigo: 'bg-indigo-400',
    cyan: 'bg-cyan-400',
  };

  const selectedVariant = variantClasses[variant] || variantClasses.neutral;
  const selectedDot = dotClasses[variant] || dotClasses.neutral;

  return (
    <span
      className={`inline-flex items-center font-medium rounded-full ${sizeClasses[size]} ${selectedVariant} ${className}`}
    >
      {dot && <span className={`w-1.5 h-1.5 rounded-full ${selectedDot}`} />}
      {children || label}
    </span>
  );
};

export const StatusBadge: React.FC<BadgeProps & { status?: string }> = ({
  status = 'active',
  children,
  variant,
  ...props
}) => {
  const norm = String(status).toLowerCase();
  let computedVariant: BadgeVariant = variant || 'neutral';

  if (!variant) {
    if (['active', 'paid', 'approved', 'completed', 'resolved', 'success', 'enabled', 'sent'].includes(norm)) {
      computedVariant = 'success';
    } else if (['pending', 'processing', 'in_review', 'warning', 'trial'].includes(norm)) {
      computedVariant = 'warning';
    } else if (['danger', 'rejected', 'failed', 'cancelled', 'overdue', 'lost', 'disabled'].includes(norm)) {
      computedVariant = 'danger';
    } else if (['info', 'qualified', 'transferred', 'open'].includes(norm)) {
      computedVariant = 'info';
    } else {
      computedVariant = 'neutral';
    }
  }

  const displayLabel = children || status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');

  return (
    <Badge variant={computedVariant} dot {...props}>
      {displayLabel}
    </Badge>
  );
};
