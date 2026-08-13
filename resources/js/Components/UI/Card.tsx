import React from 'react';
import { clsx } from 'clsx';
import { ArrowUpRight, ArrowDownRight, Minus } from 'lucide-react';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  level?: 0 | 1 | 2;
  interactive?: boolean;
  padded?: boolean;
}

export const Card: React.FC<CardProps> = ({
  children,
  className,
  level = 0,
  interactive = false,
  padded = true,
  ...props
}) => {
  const levelStyles = {
    0: 'glass-0 shadow-sm shadow-black/20',
    1: 'glass-1 shadow-lg shadow-black/40',
    2: 'glass-2 shadow-xl shadow-purple-950/20',
  };

  return (
    <div
      className={clsx(
        'rounded-xl relative overflow-hidden spring-transition',
        levelStyles[level],
        padded && 'p-5 sm:p-6',
        interactive && 'hover:border-white/20 hover:scale-[1.01] cursor-pointer active:scale-[0.99]',
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
};

export interface MetricCardProps {
  title: string;
  value: string | number;
  icon?: React.ReactNode;
  trend?: {
    value: string | number;
    positive?: boolean;
    neutral?: boolean;
    label?: string;
  };
  subtitle?: string;
  level?: 0 | 1 | 2;
  className?: string;
}

export const MetricCard: React.FC<MetricCardProps> = ({
  title,
  value,
  icon,
  trend,
  subtitle,
  level = 0,
  className,
}) => {
  return (
    <Card level={level} className={clsx('flex flex-col justify-between group', className)}>
      <div className="flex items-start justify-between gap-3 mb-3">
        <span className="text-xs font-semibold uppercase tracking-wider text-gray-400 select-none">
          {title}
        </span>
        {icon && (
          <div className="p-2 rounded-lg bg-white/[0.05] text-gray-300 group-hover:text-white group-hover:bg-white/[0.1] spring-transition">
            {icon}
          </div>
        )}
      </div>

      <div>
        <div className="text-2xl sm:text-3xl font-bold tracking-tight text-white tabular-nums mb-1">
          {value}
        </div>

        {(trend || subtitle) && (
          <div className="flex items-center gap-2 text-xs flex-wrap">
            {trend && (
              <span
                className={clsx(
                  'inline-flex items-center gap-0.5 font-semibold px-1.5 py-0.5 rounded',
                  trend.neutral
                    ? 'text-gray-400 bg-white/5'
                    : trend.positive
                    ? 'text-emerald-400 bg-emerald-500/10'
                    : 'text-rose-400 bg-rose-500/10'
                )}
              >
                {trend.neutral ? (
                  <Minus className="w-3 h-3" />
                ) : trend.positive ? (
                  <ArrowUpRight className="w-3.5 h-3.5" />
                ) : (
                  <ArrowDownRight className="w-3.5 h-3.5" />
                )}
                {trend.value}
              </span>
            )}
            {(trend?.label || subtitle) && (
              <span className="text-gray-400">{trend?.label || subtitle}</span>
            )}
          </div>
        )}
      </div>
    </Card>
  );
};
