import React from 'react';
import { clsx } from 'clsx';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'purple' | 'cyan' | 'outline';
  size?: 'sm' | 'md';
  dot?: boolean;
}

export const Badge: React.FC<BadgeProps> = ({
  children,
  className,
  variant = 'neutral',
  size = 'md',
  dot = false,
  ...props
}) => {
  const baseStyles = 'inline-flex items-center font-medium rounded-full select-none';

  const sizeStyles = {
    sm: 'text-[11px] px-2 py-0.5 gap-1',
    md: 'text-xs px-2.5 py-0.5 gap-1.5',
  };

  const variantStyles = {
    neutral: 'bg-white/[0.08] text-gray-300 border border-white/10',
    success: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/20',
    warning: 'bg-amber-500/15 text-amber-300 border border-amber-500/20',
    danger: 'bg-rose-500/15 text-rose-300 border border-rose-500/20',
    info: 'bg-blue-500/15 text-blue-300 border border-blue-500/20',
    purple: 'bg-purple-500/20 text-purple-300 border border-purple-500/30',
    cyan: 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30',
    outline: 'bg-transparent text-gray-400 border border-white/15',
  };

  const dotColor = {
    neutral: 'bg-gray-400',
    success: 'bg-emerald-400',
    warning: 'bg-amber-400',
    danger: 'bg-rose-400',
    info: 'bg-blue-400',
    purple: 'bg-purple-400',
    cyan: 'bg-cyan-400',
    outline: 'bg-gray-400',
  };

  return (
    <span className={clsx(baseStyles, sizeStyles[size], variantStyles[variant], className)} {...props}>
      {dot && <span className={clsx('w-1.5 h-1.5 rounded-full flex-shrink-0', dotColor[variant])} />}
      <span>{children}</span>
    </span>
  );
};

export interface StatusBadgeProps {
  status: string | boolean | number;
  className?: string;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status, className }) => {
  let label = String(status);
  let variant: BadgeProps['variant'] = 'neutral';

  const normalized = label.toLowerCase();

  if (['active', 'paid', 'approved', 'succeeded', 'open', 'completed', 'true', '1', 'success'].includes(normalized)) {
    variant = 'success';
    label = normalized === 'true' || normalized === '1' ? 'Active' : label;
  } else if (['pending', 'trial', 'review', 'draft', 'in_progress', 'partially_paid'].includes(normalized)) {
    variant = 'warning';
  } else if (['inactive', 'rejected', 'failed', 'cancelled', 'canceled', 'urgent', 'closed', 'false', '0'].includes(normalized)) {
    variant = 'danger';
    label = normalized === 'false' || normalized === '0' ? 'Inactive' : label;
  } else if (['sent', 'converted', 'info', 'month', 'year'].includes(normalized)) {
    variant = 'info';
  } else if (['super_admin', 'pro', 'enterprise', 'scale', 'lifetime'].includes(normalized)) {
    variant = 'purple';
  }

  return (
    <Badge variant={variant} dot size="sm" className={clsx('capitalize', className)}>
      {label.replace(/_/g, ' ')}
    </Badge>
  );
};
