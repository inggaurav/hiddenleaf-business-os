import React, { useState, useEffect, useRef } from 'react';
import { usePage, router } from '@inertiajs/react';
import { Search, Sparkles, ArrowRight, ArrowUpRight, Check, Command } from 'lucide-react';
import { ALL_NAVIGATION_GROUPS, filterNavigation, NavigationItem, NavigationGroup } from '@/Navigation/NavigationRegistry';
import { ALL_QUICK_ACTIONS, filterActions, ActionItem } from '@/Navigation/ActionRegistry';

interface CommandPaletteProps {
  isOpen: boolean;
  onClose: () => void;
  onOpenMrFox?: () => void;
}

export const CommandPalette: React.FC<CommandPaletteProps> = ({
  isOpen,
  onClose,
  onOpenMrFox,
}) => {
  const { auth, tenant } = usePage<any>().props;
  const isSuperAdmin = Boolean(auth?.user?.is_super_admin);
  const userPermissions = auth?.user?.permissions || [];
  const enabledModules = tenant?.modules || [];

  const [query, setQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);

  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLElement | null>(null);

  // Collect authorized navigation items
  const authorizedNavGroups = filterNavigation(
    ALL_NAVIGATION_GROUPS,
    auth?.user,
    isSuperAdmin,
    userPermissions,
    enabledModules
  );
  const navItems: NavigationItem[] = authorizedNavGroups.flatMap((grp: NavigationGroup) => grp.items);

  // Collect authorized quick actions
  const actionItems: ActionItem[] = filterActions(
    ALL_QUICK_ACTIONS,
    auth?.user,
    isSuperAdmin,
    userPermissions,
    enabledModules
  );

  // Combine items
  const allItems: Array<NavigationItem | ActionItem> = [...actionItems, ...navItems];

  // Filter based on search query
  const filteredItems = allItems.filter((item) => {
    const q = query.toLowerCase().trim();
    if (!q) return true;
    const matchName = item.name.toLowerCase().includes(q);
    const matchDesc = item.description?.toLowerCase().includes(q);
    const matchCategory = item.category?.toLowerCase().includes(q);
    return matchName || matchDesc || matchCategory;
  });

  // Track focused element before palette opens for restoration
  useEffect(() => {
    if (isOpen) {
      triggerRef.current = document.activeElement as HTMLElement;
      setQuery('');
      setSelectedIndex(0);
      setTimeout(() => inputRef.current?.focus(), 50);
    } else if (triggerRef.current) {
      triggerRef.current.focus();
    }
  }, [isOpen]);

  useEffect(() => {
    setSelectedIndex(0);
  }, [query]);

  const executeItem = (item: NavigationItem | ActionItem) => {
    onClose();
    if (item.href) {
      router.visit(item.href);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (filteredItems.length ? (prev + 1) % filteredItems.length : 0));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (filteredItems.length ? (prev - 1 + filteredItems.length) % filteredItems.length : 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (filteredItems[selectedIndex]) {
        executeItem(filteredItems[selectedIndex]);
      }
    } else if (e.key === 'Escape') {
      e.preventDefault();
      onClose();
    }
  };

  // Scroll active item into view
  useEffect(() => {
    if (listRef.current) {
      const activeEl = listRef.current.querySelector('[aria-selected="true"]');
      activeEl?.scrollIntoView({ block: 'nearest' });
    }
  }, [selectedIndex]);

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20 flex items-start justify-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="command-palette-title"
    >
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/60 backdrop-blur-sm spring-transition"
        onClick={onClose}
      />

      {/* Combobox Modal */}
      <div className="relative w-full max-w-2xl bg-[var(--surface-1)] border border-[var(--border-medium)] rounded-2xl shadow-2xl overflow-hidden spring-transition z-10 flex flex-col">
        <h2 id="command-palette-title" className="sr-only">Quick Command Palette</h2>

        {/* Input Bar */}
        <div className="flex items-center px-4 border-b border-[var(--border-subtle)] bg-[var(--surface-2)]">
          <Search className="w-5 h-5 text-[var(--text-tertiary)] mr-3 flex-shrink-0" />
          <input
            ref={inputRef}
            type="text"
            role="combobox"
            aria-expanded={filteredItems.length > 0}
            aria-autocomplete="list"
            aria-controls="command-palette-results"
            aria-activedescendant={filteredItems[selectedIndex]?.id || undefined}
            placeholder="Search pages, actions, or tools..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            className="w-full bg-transparent py-4 text-sm text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none"
          />
          <kbd className="hidden sm:inline-block text-[10px] font-mono bg-white/10 px-2 py-0.5 rounded text-[var(--text-tertiary)]">
            ESC
          </kbd>
        </div>

        {/* Results List */}
        <div
          id="command-palette-results"
          role="listbox"
          ref={listRef}
          className="max-h-96 overflow-y-auto p-2 space-y-1"
        >
          {filteredItems.length > 0 ? (
            filteredItems.map((item, idx) => {
              const isSelected = idx === selectedIndex;
              const Icon = item.icon;

              return (
                <div
                  key={item.id}
                  id={item.id}
                  role="option"
                  aria-selected={isSelected}
                  onClick={() => executeItem(item)}
                  onMouseEnter={() => setSelectedIndex(idx)}
                  className={`
                    flex items-center justify-between px-3 py-2.5 rounded-xl text-xs cursor-pointer select-none spring-transition
                    ${isSelected 
                      ? 'bg-purple-600/20 text-[var(--text-primary)] border border-purple-500/30' 
                      : 'text-[var(--text-secondary)] hover:bg-white/[0.04] border border-transparent'}
                  `}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <div className={`w-7 h-7 rounded-lg flex items-center justify-center ${isSelected ? 'bg-purple-600/30 text-purple-300' : 'bg-white/5 text-[var(--text-tertiary)]'}`}>
                      <Icon className="w-4 h-4" />
                    </div>
                    <div className="truncate">
                      <div className="font-semibold text-[var(--text-primary)] truncate">{item.name}</div>
                      {item.description && (
                        <div className="text-[11px] text-[var(--text-tertiary)] truncate">{item.description}</div>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center gap-2 flex-shrink-0 ml-2">
                    {item.category && (
                      <span className="text-[10px] px-2 py-0.5 rounded-full bg-[var(--surface-2)] border border-[var(--border-subtle)] text-[var(--text-tertiary)]">
                        {item.category}
                      </span>
                    )}
                    {isSelected && <ArrowRight className="w-3.5 h-3.5 text-purple-400" />}
                  </div>
                </div>
              );
            })
          ) : (
            <div className="py-12 text-center text-xs text-[var(--text-tertiary)]">
              No matching pages or actions found for &quot;{query}&quot;.
            </div>
          )}
        </div>

        {/* Footer info bar */}
        <div className="px-4 py-2 border-t border-[var(--border-subtle)] bg-[var(--surface-2)] flex items-center justify-between text-[11px] text-[var(--text-tertiary)]">
          <div className="flex items-center gap-3">
            <span>↑↓ Navigate</span>
            <span>↵ Select</span>
            <span>ESC Close</span>
          </div>
          {onOpenMrFox && (
            <button
              type="button"
              onClick={() => {
                onClose();
                onOpenMrFox();
              }}
              className="text-purple-400 hover:text-purple-300 flex items-center gap-1 font-medium cursor-pointer"
            >
              <span>Ask Mr Fox</span>
              <kbd className="text-[9px] font-mono bg-white/10 px-1 py-0.2 rounded">⌘J</kbd>
            </button>
          )}
        </div>
      </div>
    </div>
  );
};
