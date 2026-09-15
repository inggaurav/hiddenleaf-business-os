/**
 * Indian formatting helpers for HiddenLeaf BusinessOS.
 *
 * Use these everywhere instead of raw Intl.NumberFormat.
 * The old Dashboard.tsx used US Dollar formatting which is wrong for this market.
 */

/** ₹14,28,400 — full Indian digit grouping, for table cells and detail views */
export function formatINR(amount: number | string | null | undefined): string {
  const n = Number(amount);
  if (!Number.isFinite(n)) return '—';

  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
  }).format(n);
}

/** ₹14,28,400.50 — with paise, for accounting ledgers */
export function formatINRPrecise(amount: number | string | null | undefined): string {
  const n = Number(amount);
  if (!Number.isFinite(n)) return '—';

  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
}

/** ₹14.2L / ₹1.4Cr — short form for metric cards */
export function formatINRShort(amount: number | string | null | undefined): string {
  const n = Number(amount);
  if (!Number.isFinite(n)) return '—';

  const abs = Math.abs(n);
  const sign = n < 0 ? '-' : '';

  if (abs >= 10000000) return `${sign}₹${(abs / 10000000).toFixed(abs >= 100000000 ? 0 : 1)}Cr`;
  if (abs >= 100000)   return `${sign}₹${(abs / 100000).toFixed(abs >= 1000000 ? 0 : 1)}L`;
  if (abs >= 1000)     return `${sign}₹${(abs / 1000).toFixed(abs >= 10000 ? 0 : 1)}K`;

  return `${sign}₹${abs.toFixed(0)}`;
}

/** 1,28,400 — plain Indian grouping, no currency symbol */
export function formatNumber(value: number | string | null | undefined): string {
  const n = Number(value);
  if (!Number.isFinite(n)) return '—';
  return new Intl.NumberFormat('en-IN').format(n);
}

/** 94.2% — one decimal, or "—" when null */
export function formatPercent(
  value: number | null | undefined,
  opts: { alreadyPercent?: boolean } = {},
): string {
  if (value === null || value === undefined || !Number.isFinite(Number(value))) return '—';
  const n = opts.alreadyPercent ? Number(value) : Number(value) * 100;
  return `${n.toFixed(1)}%`;
}

/** "14 Sep" for current year, "14 Sep 2025" otherwise */
export function formatDate(date: string | Date | null | undefined): string {
  if (!date) return '—';
  const d = typeof date === 'string' ? new Date(date) : date;
  if (Number.isNaN(d.getTime())) return '—';

  const sameYear = d.getFullYear() === new Date().getFullYear();

  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    ...(sameYear ? {} : { year: 'numeric' }),
  }).format(d);
}

/** "14 Sep, 2:30 pm" */
export function formatDateTime(date: string | Date | null | undefined): string {
  if (!date) return '—';
  const d = typeof date === 'string' ? new Date(date) : date;
  if (Number.isNaN(d.getTime())) return '—';

  return `${formatDate(d)}, ${formatTime(d)}`;
}

/** "2:30 pm" — lowercase meridiem, Indian convention */
export function formatTime(date: string | Date | null | undefined): string {
  if (!date) return '—';
  const d = typeof date === 'string' ? new Date(date) : date;
  if (Number.isNaN(d.getTime())) return '—';

  return new Intl.DateTimeFormat('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
    .format(d)
    .toLowerCase()
    .replace(/\s/g, ' ');
}

/** "3 days ago", "in 5 days", "today" */
export function formatRelative(date: string | Date | null | undefined): string {
  if (!date) return '—';
  const d = typeof date === 'string' ? new Date(date) : date;
  if (Number.isNaN(d.getTime())) return '—';

  const diffMs = d.getTime() - Date.now();
  const diffDays = Math.round(diffMs / 86400000);

  if (diffDays === 0) return 'today';
  if (diffDays === 1) return 'tomorrow';
  if (diffDays === -1) return 'yesterday';
  if (diffDays > 0) return `in ${diffDays} days`;
  return `${Math.abs(diffDays)} days ago`;
}

/** Greeting by local hour — used on the workspace dashboard */
export function greeting(): string {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 17) return 'Good afternoon';
  return 'Good evening';
}
