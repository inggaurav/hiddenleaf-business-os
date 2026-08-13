import React from 'react';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  level?: 0 | 1 | 2;
  children: React.ReactNode;
  className?: string;
  padded?: boolean;
}

export const Card: React.FC<CardProps> = ({
  level = 0,
  children,
  className = '',
  padded = true,
  ...props
}) => {
  const levelClass =
    level === 2
      ? 'glass-2'
      : level === 1
      ? 'glass-1'
      : 'glass-0';

  return (
    <div
      className={`rounded-2xl ${levelClass} ${
        padded ? 'p-5 sm:p-6' : ''
      } spring-transition ${className}`}
      {...props}
    >
      {children}
    </div>
  );
};

export interface MetricCardProps {
  title: string;
  value: string | number;
  icon: React.ReactNode;
  trend?: string | { value: string; positive?: boolean; neutral?: boolean; label?: string };
  trendDirection?: 'up' | 'down' | 'neutral';
  subtitle?: string;
}

export const MetricCard: React.FC<MetricCardProps> = ({
  title,
  value,
  icon,
  trend,
  trendDirection = 'neutral',
  subtitle,
}) => {
  const trendText = typeof trend === 'object' && trend !== null ? trend.value : trend;
  const trendSub = typeof trend === 'object' && trend !== null ? trend.label : subtitle;
  const direction =
    typeof trend === 'object' && trend !== null
      ? trend.positive
        ? 'up'
        : trend.neutral
        ? 'neutral'
        : 'down'
      : trendDirection;

  return (
    <Card level={0} className="space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
          {title}
        </span>
        <div className="p-2 rounded-xl bg-[var(--surface-2)] text-[var(--text-secondary)] border border-[var(--border-subtle)]">
          {icon}
        </div>
      </div>

      <div className="space-y-1">
        <div className="text-2xl font-bold tracking-tight text-[var(--text-primary)]">
          {value}
        </div>
        {(trendText || trendSub) && (
          <div className="flex items-center gap-2 text-xs text-[var(--text-secondary)]">
            {trendText && (
              <span
                className={`font-semibold ${
                  direction === 'up'
                    ? 'text-emerald-400'
                    : direction === 'down'
                    ? 'text-rose-400'
                    : 'text-[var(--text-tertiary)]'
                }`}
              >
                {trendText}
              </span>
            )}
            {trendSub && <span className="text-[var(--text-tertiary)]">{trendSub}</span>}
          </div>
        )}
      </div>
    </Card>
  );
};
