import React from 'react';
import { clsx } from 'clsx';

export interface SectionHeaderProps {
  title: string;
  description?: string;
  badge?: React.ReactNode;
  actions?: React.ReactNode;
  className?: string;
}

export const SectionHeader: React.FC<SectionHeaderProps> = ({
  title,
  description,
  badge,
  actions,
  className,
}) => {
  return (
    <div className={clsx('flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6', className)}>
      <div>
        <div className="flex items-center gap-3 flex-wrap">
          <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-white">{title}</h1>
          {badge}
        </div>
        {description && <p className="text-xs sm:text-sm text-gray-400 mt-1">{description}</p>}
      </div>

      {actions && <div className="flex items-center gap-2.5 flex-wrap self-start sm:self-auto">{actions}</div>}
    </div>
  );
};

export interface EmptyStateProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  action?: React.ReactNode;
  className?: string;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  icon,
  title,
  description,
  action,
  className,
}) => {
  return (
    <div className={clsx('py-16 px-4 text-center max-w-md mx-auto flex flex-col items-center justify-center', className)}>
      <div className="p-4 rounded-2xl bg-white/[0.04] border border-white/10 text-gray-400 mb-4 shadow-inner">
        {icon}
      </div>
      <h3 className="text-lg font-semibold text-white tracking-tight">{title}</h3>
      <p className="text-sm text-gray-400 mt-1.5 mb-6 leading-relaxed">{description}</p>
      {action}
    </div>
  );
};

export const Skeleton: React.FC<{ className?: string }> = ({ className }) => {
  return <div className={clsx('animate-pulse bg-white/[0.06] rounded-lg', className)} />;
};
