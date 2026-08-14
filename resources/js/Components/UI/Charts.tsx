import React from 'react';

export interface BarChartItem {
  label: string;
  value: number;
  secondaryValue?: number;
  formattedValue?: string;
  secondaryFormattedValue?: string;
}

export interface BarChartProps {
  data: BarChartItem[];
  primaryLabel?: string;
  secondaryLabel?: string;
  primaryColor?: string;
  secondaryColor?: string;
  height?: number;
  emptyMessage?: string;
}

export const SimpleBarChart: React.FC<BarChartProps> = ({
  data,
  primaryLabel = 'Amount',
  secondaryLabel,
  primaryColor = 'bg-emerald-500',
  secondaryColor = 'bg-sky-500',
  height = 180,
  emptyMessage = 'No trend data available for this period.',
}) => {
  if (!data || data.length === 0) {
    return (
      <div className="flex h-44 items-center justify-center rounded-xl border border-dashed border-[var(--border-subtle)] p-6 text-center text-xs text-[var(--text-tertiary)]">
        {emptyMessage}
      </div>
    );
  }

  const maxValue = Math.max(
    ...data.map((d) => Math.max(d.value || 0, d.secondaryValue || 0)),
    1
  );

  return (
    <div className="space-y-4">
      {(primaryLabel || secondaryLabel) && (
        <div className="flex items-center gap-4 text-xs">
          <div className="flex items-center gap-1.5">
            <span className={`h-2.5 w-2.5 rounded-full ${primaryColor}`} />
            <span className="text-[var(--text-secondary)]">{primaryLabel}</span>
          </div>
          {secondaryLabel && (
            <div className="flex items-center gap-1.5">
              <span className={`h-2.5 w-2.5 rounded-full ${secondaryColor}`} />
              <span className="text-[var(--text-secondary)]">{secondaryLabel}</span>
            </div>
          )}
        </div>
      )}

      <div
        className="flex items-end justify-between gap-2 border-b border-[var(--border-subtle)] pb-2 pt-4 px-1"
        style={{ height }}
      >
        {data.map((item, idx) => {
          const pHeight = Math.max(Math.round(((item.value || 0) / maxValue) * 100), item.value > 0 ? 4 : 0);
          const sHeight = item.secondaryValue !== undefined
            ? Math.max(Math.round(((item.secondaryValue || 0) / maxValue) * 100), item.secondaryValue > 0 ? 4 : 0)
            : null;

          return (
            <div key={idx} className="group relative flex flex-1 flex-col items-center gap-1">
              {/* Tooltip on hover */}
              <div className="pointer-events-none absolute -top-10 z-20 hidden rounded-md bg-[var(--surface-3)] px-2 py-1 text-[10px] font-medium text-[var(--text-primary)] shadow-lg border border-[var(--border-subtle)] whitespace-nowrap group-hover:flex">
                <span>{item.label}: {item.formattedValue ?? item.value}</span>
                {item.secondaryFormattedValue && (
                  <span className="ml-1 text-[var(--text-secondary)]">({item.secondaryFormattedValue})</span>
                )}
              </div>

              <div className="flex w-full items-end justify-center gap-1 h-full">
                <div
                  className={`w-full max-w-[20px] rounded-t-sm transition-all duration-300 group-hover:opacity-80 ${primaryColor}`}
                  style={{ height: `${pHeight}%` }}
                />
                {sHeight !== null && (
                  <div
                    className={`w-full max-w-[20px] rounded-t-sm transition-all duration-300 group-hover:opacity-80 ${secondaryColor}`}
                    style={{ height: `${sHeight}%` }}
                  />
                )}
              </div>

              <span className="text-[11px] text-[var(--text-tertiary)] truncate max-w-full">
                {item.label}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
};

export interface ProgressDistributionItem {
  name: string;
  value: number;
  formattedValue?: string;
  color?: string;
}

export interface ProgressDistributionProps {
  items: ProgressDistributionItem[];
  totalLabel?: string;
}

export const ProgressDistribution: React.FC<ProgressDistributionProps> = ({
  items,
}) => {
  const total = items.reduce((acc, curr) => acc + (curr.value || 0), 0);
  const defaultColors = [
    'bg-emerald-500',
    'bg-sky-500',
    'bg-amber-500',
    'bg-indigo-500',
    'bg-rose-500',
    'bg-purple-500',
  ];

  return (
    <div className="space-y-3">
      {/* Progress bar stack */}
      <div className="flex h-3 w-full overflow-hidden rounded-full bg-[var(--surface-2)]">
        {items.map((item, idx) => {
          const pct = total > 0 ? (item.value / total) * 100 : 0;
          if (pct === 0) return null;
          const colorClass = item.color || defaultColors[idx % defaultColors.length];
          return (
            <div
              key={idx}
              className={`h-full ${colorClass} transition-all duration-500`}
              style={{ width: `${pct}%` }}
              title={`${item.name}: ${item.formattedValue ?? item.value} (${pct.toFixed(1)}%)`}
            />
          );
        })}
      </div>

      {/* Legend */}
      <div className="grid grid-cols-2 gap-2 text-xs">
        {items.map((item, idx) => {
          const pct = total > 0 ? ((item.value / total) * 100).toFixed(1) : '0.0';
          const colorClass = item.color || defaultColors[idx % defaultColors.length];
          return (
            <div key={idx} className="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-[var(--surface-1)] border border-[var(--border-subtle)]">
              <div className="flex items-center gap-2 truncate">
                <span className={`h-2.5 w-2.5 rounded-sm ${colorClass} shrink-0`} />
                <span className="text-[var(--text-secondary)] truncate">{item.name}</span>
              </div>
              <span className="font-medium text-[var(--text-primary)] shrink-0">
                {item.formattedValue ?? item.value} <span className="text-[10px] text-[var(--text-tertiary)]">({pct}%)</span>
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
};
