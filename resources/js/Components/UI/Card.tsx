import React from 'react';

// ── Card ──────────────────────────────────────────────────────────────────────

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
    level === 2 ? 'glass-2' : level === 1 ? 'glass-1' : 'glass-0';

  return (
    <div
      className={`rounded-2xl ${levelClass} ${padded ? 'p-5 sm:p-6' : ''} spring-transition ${className}`}
      {...props}
    >
      {children}
    </div>
  );
};

// ── CardHeader — use inside padded={false} Card ───────────────────────────────

interface CardHeaderProps {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
}

export const CardHeader: React.FC<CardHeaderProps> = ({ title, subtitle, actions }) => (
  <div className="px-5 py-4 border-b border-[var(--border-subtle)] flex items-center justify-between gap-4">
    <div>
      <h3 className="text-sm font-semibold text-[var(--text-primary)] leading-tight">{title}</h3>
      {subtitle && (
        <p className="text-xs text-[var(--text-tertiary)] mt-0.5 leading-snug">{subtitle}</p>
      )}
    </div>
    {actions && <div className="flex items-center gap-2 flex-shrink-0">{actions}</div>}
  </div>
);

export const CardBody: React.FC<{ children: React.ReactNode; className?: string }> = ({
  children,
  className = '',
}) => <div className={`p-5 ${className}`}>{children}</div>;

// ── MetricCard ────────────────────────────────────────────────────────────────

export interface MetricCardProps {
  title: string;
  value: string | number;
  icon?: React.ReactNode;
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
      ? trend.positive ? 'up' : trend.neutral ? 'neutral' : 'down'
      : trendDirection;

  return (
    <Card level={0} className="space-y-3">
      {/* Label row */}
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium uppercase tracking-wide text-[var(--text-tertiary)] select-none">
          {title}
        </span>
        {icon && (
          <div className="w-8 h-8 rounded-lg bg-[var(--surface-2)] border border-[var(--border-subtle)] flex items-center justify-center text-[var(--text-secondary)]">
            {icon}
          </div>
        )}
      </div>

      {/* Value — larger than before, tabular for financial data */}
      <div className="text-3xl font-bold tracking-tight tabular-nums text-[var(--text-primary)] leading-none">
        {value}
      </div>

      {/* Trend / subtitle */}
      {(trendText || trendSub) && (
        <div className="flex items-center gap-1.5 text-xs">
          {trendText && (
            <span
              className={`font-medium ${
                direction === 'up'
                  ? 'text-emerald-400'
                  : direction === 'down'
                  ? 'text-rose-400'
                  : 'text-[var(--text-tertiary)]'
              }`}
            >
              {direction === 'up' ? '↑' : direction === 'down' ? '↓' : ''} {trendText}
            </span>
          )}
          {trendSub && <span className="text-[var(--text-tertiary)]">{trendSub}</span>}
        </div>
      )}
    </Card>
  );
};
