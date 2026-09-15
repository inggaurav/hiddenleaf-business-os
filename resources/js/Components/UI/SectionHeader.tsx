import React from 'react';

interface SectionHeaderProps {
  title: string;
  description?: string;
  badge?: React.ReactNode;
  actions?: React.ReactNode;
}

/**
 * One per page, at the very top of the page content area.
 *
 * Rules:
 * - title: sentence case, no trailing punctuation
 * - description: one line, present tense, ≤ 60 chars
 * - actions: max 2 buttons, right-aligned
 */
export const SectionHeader: React.FC<SectionHeaderProps> = ({
  title,
  description,
  badge,
  actions,
}) => {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[var(--border-subtle)]">
      <div className="space-y-1 min-w-0">
        <div className="flex items-center gap-2.5 flex-wrap">
          {/* text-xl = 20px at 700 weight — visible enough without being aggressive */}
          <h1 className="text-xl font-bold tracking-tight text-[var(--text-primary)] leading-tight">
            {title}
          </h1>
          {badge}
        </div>
        {description && (
          <p className="text-sm text-[var(--text-secondary)] leading-snug">{description}</p>
        )}
      </div>

      {actions && (
        <div className="flex items-center gap-2 flex-shrink-0">
          {actions}
        </div>
      )}
    </div>
  );
};
