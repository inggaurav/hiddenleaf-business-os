import React, { useState, useRef, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import { ChevronDown, Check, Plus, Layers, Search } from 'lucide-react';

interface WorkspaceItem {
  id: number;
  name: string;
}

export const WorkspaceSwitcher: React.FC = () => {
  const { tenant } = usePage<any>().props;
  const activeTitle = tenant?.workspace_title || 'No workspace selected';
  const activeId = tenant?.workspace_id;
  const workspaces: WorkspaceItem[] = tenant?.available_workspaces || [];

  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [focusedIndex, setFocusedIndex] = useState(0);

  const containerRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  const filteredWorkspaces = workspaces.filter((ws) =>
    ws.name.toLowerCase().includes(search.toLowerCase())
  );

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (isOpen) {
      setSearch('');
      setFocusedIndex(0);
      setTimeout(() => searchInputRef.current?.focus(), 50);
    } else {
      buttonRef.current?.focus();
    }
  }, [isOpen]);

  const handleSwitch = (id: number) => {
    setIsOpen(false);
    router.post('/workspaces/switch', { workspace_id: id });
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      setIsOpen(false);
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      setFocusedIndex((prev) => (filteredWorkspaces.length ? (prev + 1) % filteredWorkspaces.length : 0));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setFocusedIndex((prev) => (filteredWorkspaces.length ? (prev - 1 + filteredWorkspaces.length) % filteredWorkspaces.length : 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (filteredWorkspaces[focusedIndex]) {
        handleSwitch(filteredWorkspaces[focusedIndex].id);
      }
    }
  };

  return (
    <div className="relative min-w-0 text-left" ref={containerRef}>
      <button
        ref={buttonRef}
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
        aria-label={`Current Workspace: ${activeTitle}. Click to switch workspace.`}
        className="flex min-w-0 items-center gap-1.5 px-2 py-1.5 sm:gap-2.5 sm:px-3 rounded-xl bg-[var(--surface-1)] hover:bg-[var(--surface-2)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] spring-transition text-xs font-semibold text-[var(--text-primary)] cursor-pointer"
      >
        <div className="w-5 h-5 rounded-lg bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white shadow-sm flex-shrink-0">
          <Layers className="w-3 h-3" />
        </div>
        <span className="truncate max-w-[92px] min-[430px]:max-w-[140px] sm:max-w-[200px] text-left">{activeTitle}</span>
        <ChevronDown className={`w-3.5 h-3.5 text-[var(--text-tertiary)] spring-transition ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <div
          role="listbox"
          aria-label="Authorized Workspaces"
          onKeyDown={handleKeyDown}
          className="absolute left-0 mt-2 w-64 rounded-2xl bg-[var(--surface-1)] border border-[var(--border-medium)] shadow-2xl p-2 z-50 spring-transition"
        >
          <div className="px-2 pb-2 mb-1 border-b border-[var(--border-subtle)]">
            <div className="flex items-center gap-2 px-2 py-1 rounded-lg bg-[var(--surface-2)] border border-[var(--border-subtle)]">
              <Search className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
              <input
                ref={searchInputRef}
                type="text"
                placeholder="Find workspace..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full bg-transparent text-xs text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none"
              />
            </div>
          </div>

          <div className="max-h-48 overflow-y-auto space-y-1">
            {filteredWorkspaces.length > 0 ? (
              filteredWorkspaces.map((ws, idx) => {
                const isActive = ws.id === Number(activeId);
                const isFocused = idx === focusedIndex;

                return (
                  <button
                    key={ws.id}
                    type="button"
                    role="option"
                    aria-selected={isActive}
                    onClick={() => handleSwitch(ws.id)}
                    onMouseEnter={() => setFocusedIndex(idx)}
                    className={`
                      w-full flex items-center justify-between px-2.5 py-2 rounded-xl text-xs text-left spring-transition cursor-pointer
                      ${isActive ? 'bg-purple-600/20 text-[var(--text-primary)] font-semibold border border-purple-500/30' : ''}
                      ${isFocused && !isActive ? 'bg-white/[0.05] text-[var(--text-primary)]' : 'text-[var(--text-secondary)]'}
                    `}
                  >
                    <span className="truncate">{ws.name}</span>
                    {isActive && <Check className="w-3.5 h-3.5 text-purple-400 flex-shrink-0" />}
                  </button>
                );
              })
            ) : (
              <div className="px-3 py-4 text-center text-xs text-[var(--text-tertiary)]">
                {workspaces.length === 0 ? 'No other workspaces available' : 'No matching workspace'}
              </div>
            )}
          </div>

          <div className="pt-2 mt-1 border-t border-[var(--border-subtle)]">
            <a
              href="/workspaces"
              className="flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-medium text-purple-400 hover:text-purple-300 hover:bg-purple-600/10 spring-transition"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Manage Workspaces</span>
            </a>
          </div>
        </div>
      )}
    </div>
  );
};
