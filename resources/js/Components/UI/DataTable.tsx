import React, { useState, useMemo } from 'react';
import { clsx } from 'clsx';
import { 
  Search, 
  ChevronLeft, 
  ChevronRight, 
  ArrowUpDown, 
  ArrowUp, 
  ArrowDown, 
  Inbox,
  Filter
} from 'lucide-react';
import { Input } from './Input';
import { Button } from './Button';
import { Card } from './Card';

export interface Column<T> {
  key: string;
  header: string;
  render?: (row: T) => React.ReactNode;
  sortable?: boolean;
  className?: string;
}

export interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  searchable?: boolean;
  searchPlaceholder?: string;
  searchKeys?: string[];
  filterComponent?: React.ReactNode;
  actions?: React.ReactNode;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyAction?: React.ReactNode;
  pageSize?: number;
  onRowClick?: (row: T) => void;
  className?: string;
}

export function DataTable<T extends Record<string, any>>({
  columns,
  data = [],
  searchable = true,
  searchPlaceholder = 'Search records...',
  searchKeys,
  filterComponent,
  actions,
  emptyTitle = 'No records found',
  emptyDescription = 'Try adjusting your search query or filters.',
  emptyAction,
  pageSize = 10,
  onRowClick,
  className,
}: DataTableProps<T>) {
  const [searchQuery, setSearchQuery] = useState('');
  const [sortKey, setSortKey] = useState<string | null>(null);
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
  const [currentPage, setCurrentPage] = useState(1);

  // Search Filter
  const filteredData = useMemo(() => {
    if (!searchQuery.trim()) return data;

    const query = searchQuery.toLowerCase();
    return data.filter((row) => {
      if (searchKeys && searchKeys.length > 0) {
        return searchKeys.some((key) => {
          const val = row[key];
          return val !== null && val !== undefined && String(val).toLowerCase().includes(query);
        });
      }

      // Default: search all string/number columns
      return Object.values(row).some((val) => {
        return val !== null && val !== undefined && String(val).toLowerCase().includes(query);
      });
    });
  }, [data, searchQuery, searchKeys]);

  // Sorting
  const sortedData = useMemo(() => {
    if (!sortKey) return filteredData;

    return [...filteredData].sort((a, b) => {
      const aVal = a[sortKey];
      const bVal = b[sortKey];

      if (aVal === bVal) return 0;
      if (aVal === null || aVal === undefined) return 1;
      if (bVal === null || bVal === undefined) return -1;

      if (typeof aVal === 'number' && typeof bVal === 'number') {
        return sortOrder === 'asc' ? aVal - bVal : bVal - aVal;
      }

      return sortOrder === 'asc'
        ? String(aVal).localeCompare(String(bVal))
        : String(bVal).localeCompare(String(aVal));
    });
  }, [filteredData, sortKey, sortOrder]);

  // Pagination
  const totalPages = Math.ceil(sortedData.length / pageSize) || 1;
  const paginatedData = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return sortedData.slice(start, start + pageSize);
  }, [sortedData, currentPage, pageSize]);

  const handleSort = (key: string) => {
    if (sortKey === key) {
      if (sortOrder === 'asc') {
        setSortOrder('desc');
      } else {
        setSortKey(null);
      }
    } else {
      setSortKey(key);
      setSortOrder('asc');
    }
  };

  return (
    <Card level={0} padded={false} className={clsx('w-full overflow-hidden', className)}>
      {/* Table Toolbar */}
      {(searchable || filterComponent || actions) && (
        <div className="p-4 sm:p-5 border-b border-white/10 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <div className="flex items-center gap-3 flex-1">
            {searchable && (
              <div className="w-full sm:max-w-xs">
                <Input
                  placeholder={searchPlaceholder}
                  leftIcon={<Search className="w-4 h-4 text-gray-400" />}
                  value={searchQuery}
                  onChange={(e) => {
                    setSearchQuery(e.target.value);
                    setCurrentPage(1);
                  }}
                  onClear={() => setSearchQuery('')}
                />
              </div>
            )}
            {filterComponent}
          </div>

          {actions && <div className="flex items-center gap-2 self-end sm:self-auto">{actions}</div>}
        </div>
      )}

      {/* Desktop Table View */}
      <div className="hidden md:block overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="border-b border-white/10 bg-white/[0.02] text-xs font-semibold text-gray-400 uppercase tracking-wider select-none">
              {columns.map((col) => (
                <th
                  key={col.key}
                  className={clsx(
                    'py-3.5 px-4 font-medium',
                    col.sortable && 'cursor-pointer hover:text-white spring-transition',
                    col.className
                  )}
                  onClick={() => col.sortable && handleSort(col.key)}
                >
                  <div className="flex items-center gap-1.5">
                    <span>{col.header}</span>
                    {col.sortable && (
                      <span className="text-gray-500">
                        {sortKey === col.key ? (
                          sortOrder === 'asc' ? (
                            <ArrowUp className="w-3.5 h-3.5 text-violet-400" />
                          ) : (
                            <ArrowDown className="w-3.5 h-3.5 text-violet-400" />
                          )
                        ) : (
                          <ArrowUpDown className="w-3.5 h-3.5 opacity-40 hover:opacity-100" />
                        )}
                      </span>
                    )}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-white/[0.06] text-sm text-gray-200">
            {paginatedData.length > 0 ? (
              paginatedData.map((row, idx) => (
                <tr
                  key={row.id || idx}
                  onClick={() => onRowClick && onRowClick(row)}
                  className={clsx(
                    'hover:bg-white/[0.04] spring-transition group',
                    onRowClick && 'cursor-pointer'
                  )}
                >
                  {columns.map((col) => (
                    <td key={col.key} className={clsx('py-3.5 px-4', col.className)}>
                      {col.render ? col.render(row) : row[col.key] ?? '—'}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={columns.length} className="py-16 text-center">
                  <div className="flex flex-col items-center justify-center max-w-sm mx-auto">
                    <div className="p-3 rounded-2xl bg-white/[0.04] border border-white/10 text-gray-400 mb-3">
                      <Inbox className="w-8 h-8" />
                    </div>
                    <h4 className="text-base font-semibold text-white">{emptyTitle}</h4>
                    <p className="text-xs text-gray-400 mt-1 mb-4">{emptyDescription}</p>
                    {emptyAction}
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Mobile Card View */}
      <div className="md:hidden divide-y divide-white/[0.06]">
        {paginatedData.length > 0 ? (
          paginatedData.map((row, idx) => (
            <div
              key={row.id || idx}
              onClick={() => onRowClick && onRowClick(row)}
              className="p-4 space-y-2 hover:bg-white/[0.02]"
            >
              {columns.map((col) => (
                <div key={col.key} className="flex items-center justify-between text-xs">
                  <span className="text-gray-400 font-medium">{col.header}</span>
                  <span className="text-gray-200 text-right font-medium">
                    {col.render ? col.render(row) : row[col.key] ?? '—'}
                  </span>
                </div>
              ))}
            </div>
          ))
        ) : (
          <div className="py-12 px-4 text-center">
            <div className="p-3 rounded-2xl bg-white/[0.04] border border-white/10 text-gray-400 mx-auto w-fit mb-3">
              <Inbox className="w-6 h-6" />
            </div>
            <h4 className="text-sm font-semibold text-white">{emptyTitle}</h4>
            <p className="text-xs text-gray-400 mt-1 mb-3">{emptyDescription}</p>
            {emptyAction}
          </div>
        )}
      </div>

      {/* Pagination Footer */}
      {sortedData.length > pageSize && (
        <div className="p-4 border-t border-white/10 flex items-center justify-between text-xs text-gray-400">
          <div>
            Showing <span className="text-white font-medium">{(currentPage - 1) * pageSize + 1}</span> to{' '}
            <span className="text-white font-medium">
              {Math.min(currentPage * pageSize, sortedData.length)}
            </span>{' '}
            of <span className="text-white font-medium">{sortedData.length}</span> results
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage <= 1}
              onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
            >
              <ChevronLeft className="w-4 h-4" />
            </Button>
            <span className="px-2 font-medium text-gray-300">
              {currentPage} / {totalPages}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage >= totalPages}
              onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))}
            >
              <ChevronRight className="w-4 h-4" />
            </Button>
          </div>
        </div>
      )}
    </Card>
  );
}
