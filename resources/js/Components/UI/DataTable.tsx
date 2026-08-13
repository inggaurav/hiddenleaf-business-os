import React, { useState } from 'react';
import { 
  ChevronUp, 
  ChevronDown, 
  Search, 
  SlidersHorizontal,
  ChevronLeft,
  ChevronRight,
  Inbox
} from 'lucide-react';
import { Card } from './Card';
import { Button } from './Button';

export interface Column<T = any> {
  header: string;
  key?: string;
  accessorKey?: keyof T | string;
  render?: (row: T) => React.ReactNode;
  sortable?: boolean;
  align?: 'left' | 'center' | 'right';
  className?: string;
  mobileRole?: 'primary' | 'secondary' | 'status' | 'meta' | 'action' | 'hidden';
  mobileRender?: (row: T) => React.ReactNode;
}

export interface DataTableProps<T = any> {
  data: T[];
  columns: Column<T>[];
  keyExtractor?: (row: T) => string | number;
  searchable?: boolean;
  searchPlaceholder?: string;
  searchKeys?: string[];
  pageSize?: number;
  emptyMessage?: string;
  emptySubtitle?: string;
  emptyTitle?: string;
  emptyDescription?: string;
  headerActions?: React.ReactNode;
}

export function DataTable<T = any>({
  data = [],
  columns = [],
  keyExtractor,
  searchable = true,
  searchPlaceholder = 'Search records...',
  searchKeys,
  pageSize = 10,
  emptyMessage = 'No records found',
  emptySubtitle = 'There are no items matching the current view.',
  emptyTitle,
  emptyDescription,
  headerActions,
}: DataTableProps<T>) {
  const [searchTerm, setSearchTerm] = useState('');
  const [sortKey, setSortKey] = useState<string | keyof T | null>(null);
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
  const [currentPage, setCurrentPage] = useState(1);

  const finalEmptyTitle = emptyTitle || emptyMessage;
  const finalEmptySubtitle = emptyDescription || emptySubtitle;

  const defaultKeyExtractor = (row: any) => row.id || row.key || row.uuid || JSON.stringify(row);
  const resolveKey = keyExtractor || defaultKeyExtractor;

  // Filter
  const filteredData = React.useMemo(() => {
    if (!searchTerm.trim()) return data;
    const q = searchTerm.toLowerCase();

    return data.filter((item: any) => {
      if (searchKeys && searchKeys.length > 0) {
        return searchKeys.some((k) => {
          const val = item[k];
          return val !== null && val !== undefined && String(val).toLowerCase().includes(q);
        });
      }

      return Object.values(item as Record<string, unknown>).some((val) => {
        if (val === null || val === undefined) return false;
        return String(val).toLowerCase().includes(q);
      });
    });
  }, [data, searchTerm, searchKeys]);

  // Sort
  const sortedData = React.useMemo(() => {
    if (!sortKey) return filteredData;
    return [...filteredData].sort((a: any, b: any) => {
      const aVal = a[sortKey];
      const bVal = b[sortKey];
      if (aVal === bVal) return 0;
      if (aVal === null || aVal === undefined) return 1;
      if (bVal === null || bVal === undefined) return -1;
      
      const comparison = aVal < bVal ? -1 : 1;
      return sortOrder === 'asc' ? comparison : -comparison;
    });
  }, [filteredData, sortKey, sortOrder]);

  // Paginate
  const totalPages = Math.ceil(sortedData.length / pageSize) || 1;
  const paginatedData = React.useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return sortedData.slice(start, start + pageSize);
  }, [sortedData, currentPage, pageSize]);

  const handleSort = (column: Column<T>) => {
    const key = (column.accessorKey || column.key) as string | keyof T | undefined;
    if (!column.sortable || !key) return;
    if (sortKey === key) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortKey(key);
      setSortOrder('asc');
    }
  };

  // Mobile rendering extraction
  const primaryCol = columns.find((c) => c.mobileRole === 'primary') || columns[0];
  const statusCol = columns.find((c) => c.mobileRole === 'status');
  const actionCol = columns.find((c) => c.mobileRole === 'action') || columns[columns.length - 1];
  const metaCols = columns.filter((c) => 
    c.mobileRole === 'meta' || 
    (!c.mobileRole && c !== primaryCol && c !== statusCol && c !== actionCol)
  ).slice(0, 3);

  return (
    <Card level={0} padded={false} className="overflow-hidden">
      {/* Top Search & Filter Bar */}
      {(searchable || headerActions) && (
        <div className="p-4 border-b border-[var(--border-subtle)] flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-[var(--surface-1)]">
          {searchable ? (
            <div className="relative flex-1 max-w-sm">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-tertiary)]" />
              <input
                type="text"
                placeholder={searchPlaceholder}
                value={searchTerm}
                onChange={(e) => {
                  setSearchTerm(e.target.value);
                  setCurrentPage(1);
                }}
                className="w-full bg-[var(--surface-2)] border border-[var(--border-subtle)] focus:border-purple-500 rounded-xl pl-9 pr-4 py-1.5 text-xs text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none spring-transition"
              />
            </div>
          ) : <div />}

          {headerActions && (
            <div className="flex items-center gap-2">
              {headerActions}
            </div>
          )}
        </div>
      )}

      {/* Desktop & Tablet Table Surface */}
      <div className="hidden md:block overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="border-b border-[var(--border-subtle)] bg-[var(--surface-2)] text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">
              {columns.map((col, idx) => {
                const effectiveKey = (col.accessorKey || col.key) as string | keyof T | undefined;
                const isSorted = sortKey && effectiveKey && sortKey === effectiveKey;
                const ariaSortValue = !col.sortable 
                  ? undefined 
                  : isSorted 
                    ? (sortOrder === 'asc' ? 'ascending' : 'descending') 
                    : 'none';

                return (
                  <th
                    key={idx}
                    scope="col"
                    aria-sort={ariaSortValue}
                    onClick={() => handleSort(col)}
                    className={`
                      px-4 py-3 select-none
                      ${col.sortable ? 'cursor-pointer hover:text-[var(--text-primary)]' : ''}
                      ${col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left'}
                      ${col.className || ''}
                    `}
                  >
                    <div className={`inline-flex items-center gap-1.5 ${col.align === 'right' ? 'flex-row-reverse' : ''}`}>
                      <span>{col.header}</span>
                      {col.sortable && (
                        <div className="flex flex-col text-[8px] text-[var(--text-tertiary)]">
                          {isSorted ? (
                            sortOrder === 'asc' ? <ChevronUp className="w-3 h-3 text-purple-400" /> : <ChevronDown className="w-3 h-3 text-purple-400" />
                          ) : (
                            <SlidersHorizontal className="w-2.5 h-2.5 opacity-40" />
                          )}
                        </div>
                      )}
                    </div>
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border-subtle)] text-xs text-[var(--text-primary)]">
            {paginatedData.length > 0 ? (
              paginatedData.map((row) => (
                <tr
                  key={resolveKey(row)}
                  className="hover:bg-white/[0.02] spring-transition group"
                >
                  {columns.map((col, idx) => {
                    const key = col.accessorKey || col.key;
                    return (
                      <td
                        key={idx}
                        className={`
                          px-4 py-3.5
                          ${col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left'}
                          ${col.className || ''}
                        `}
                      >
                        {col.render
                          ? col.render(row)
                          : key
                          ? String((row as any)[key] ?? '—')
                          : null}
                      </td>
                    );
                  })}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={columns.length || 1} className="py-16 text-center">
                  <div className="max-w-xs mx-auto space-y-2">
                    <Inbox className="w-8 h-8 text-[var(--text-tertiary)] mx-auto opacity-50" />
                    <p className="text-sm font-semibold text-[var(--text-primary)]">{finalEmptyTitle}</p>
                    <p className="text-xs text-[var(--text-tertiary)]">{finalEmptySubtitle}</p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Mobile Card Deck Surface */}
      <div className="md:hidden divide-y divide-[var(--border-subtle)]">
        {paginatedData.length > 0 ? (
          paginatedData.map((row) => (
            <div key={resolveKey(row)} className="p-4 space-y-3 bg-[var(--surface-1)]">
              {/* Primary Identifier & Status Badge */}
              <div className="flex items-start justify-between gap-2">
                <div className="font-semibold text-sm text-[var(--text-primary)]">
                  {primaryCol?.mobileRender 
                    ? primaryCol.mobileRender(row) 
                    : primaryCol?.render 
                    ? primaryCol.render(row) 
                    : primaryCol ? String((row as any)[primaryCol.accessorKey || primaryCol.key || ''] ?? '—')
                    : null}
                </div>
                {statusCol && (
                  <div className="flex-shrink-0">
                    {statusCol.mobileRender 
                      ? statusCol.mobileRender(row) 
                      : statusCol.render 
                      ? statusCol.render(row) 
                      : String((row as any)[statusCol.accessorKey || statusCol.key || ''] ?? '')}
                  </div>
                )}
              </div>

              {/* 2-3 Relevant Metadata Fields */}
              {metaCols.length > 0 && (
                <div className="grid grid-cols-2 gap-2 text-xs bg-[var(--surface-2)] p-2.5 rounded-xl border border-[var(--border-subtle)]">
                  {metaCols.map((col, idx) => (
                    <div key={idx} className="space-y-0.5 min-w-0">
                      <span className="text-[10px] text-[var(--text-tertiary)] uppercase tracking-wider block truncate">
                        {col.header}
                      </span>
                      <div className="text-[var(--text-secondary)] truncate">
                        {col.mobileRender 
                          ? col.mobileRender(row) 
                          : col.render 
                          ? col.render(row) 
                          : String((row as any)[col.accessorKey || col.key || ''] ?? '—')}
                      </div>
                    </div>
                  ))}
                </div>
              )}

              {/* Mobile Actions Footer */}
              {actionCol && actionCol.render && (
                <div className="flex items-center justify-end gap-2 pt-1 border-t border-[var(--border-subtle)]">
                  {actionCol.mobileRender ? actionCol.mobileRender(row) : actionCol.render(row)}
                </div>
              )}
            </div>
          ))
        ) : (
          <div className="py-12 px-4 text-center space-y-2">
            <Inbox className="w-8 h-8 text-[var(--text-tertiary)] mx-auto opacity-50" />
            <p className="text-sm font-semibold text-[var(--text-primary)]">{finalEmptyTitle}</p>
            <p className="text-xs text-[var(--text-tertiary)]">{finalEmptySubtitle}</p>
          </div>
        )}
      </div>

      {/* Pagination Controls */}
      {totalPages > 1 && (
        <div className="p-3 border-t border-[var(--border-subtle)] flex items-center justify-between text-xs text-[var(--text-secondary)] bg-[var(--surface-1)]">
          <div>
            Showing <span className="font-semibold text-[var(--text-primary)]">{(currentPage - 1) * pageSize + 1}</span> to{' '}
            <span className="font-semibold text-[var(--text-primary)]">{Math.min(currentPage * pageSize, sortedData.length)}</span> of{' '}
            <span className="font-semibold text-[var(--text-primary)]">{sortedData.length}</span> results
          </div>

          <div className="flex items-center gap-1">
            <button
              type="button"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
              className="p-1 rounded-lg hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent spring-transition text-[var(--text-secondary)] hover:text-[var(--text-primary)] cursor-pointer disabled:cursor-not-allowed"
              aria-label="Previous Page"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>

            <span className="px-2 font-mono text-[11px] text-[var(--text-tertiary)]">
              {currentPage} / {totalPages}
            </span>

            <button
              type="button"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))}
              className="p-1 rounded-lg hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent spring-transition text-[var(--text-secondary)] hover:text-[var(--text-primary)] cursor-pointer disabled:cursor-not-allowed"
              aria-label="Next Page"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </Card>
  );
}
