import React, { useState, useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Layers, Check, Search, Plus, ChevronDown } from 'lucide-react';
import { Badge } from '../UI/Badge';

export interface WorkspaceItem {
  id: number;
  name: string;
  slug?: string;
  is_active?: boolean;
}

interface WorkspaceSwitcherProps {
  currentWorkspaceTitle?: string;
  workspaces?: WorkspaceItem[];
  className?: string;
}

export const WorkspaceSwitcher: React.FC<WorkspaceSwitcherProps> = ({
  currentWorkspaceTitle = 'Main Workspace',
  workspaces = [],
  className,
}) => {
  const { tenant } = usePage<any>().props;
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSwitch = (wsId: number) => {
    setOpen(false);
    router.post('/workspaces/switch', { workspace_id: wsId });
  };

  const filteredWorkspaces = workspaces.filter((ws) =>
    ws.name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div ref={dropdownRef} className="relative inline-block text-left">
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        aria-expanded={open}
        aria-haspopup="listbox"
        className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-xs font-semibold text-gray-200 hover:text-white spring-transition cursor-pointer"
      >
        <Layers className="w-3.5 h-3.5 text-purple-400" />
        <span className="truncate max-w-[140px] sm:max-w-[200px]">
          {tenant?.workspace_title || currentWorkspaceTitle}
        </span>
        <ChevronDown className="w-3.5 h-3.5 text-gray-500" />
      </button>

      {open && (
        <div
          role="listbox"
          className="absolute left-0 mt-2 w-64 glass-dropdown rounded-xl border border-white/15 shadow-2xl p-2 z-50 animate-in zoom-in-95 duration-150 space-y-2"
        >
          <div className="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-white/[0.04] border border-white/10">
            <Search className="w-3.5 h-3.5 text-gray-500" />
            <input
              autoFocus
              type="text"
              placeholder="Find workspace..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-transparent text-xs text-white placeholder:text-gray-500 outline-none"
            />
          </div>

          <div className="max-h-48 overflow-y-auto space-y-0.5 pr-1">
            {filteredWorkspaces.length > 0 ? (
              filteredWorkspaces.map((ws) => {
                const isSelected = tenant?.workspace_title === ws.name || ws.id === tenant?.active_workspace_id;
                return (
                  <div
                    key={ws.id}
                    role="option"
                    aria-selected={isSelected}
                    onClick={() => handleSwitch(ws.id)}
                    className={`
                      px-3 py-2 rounded-lg text-xs font-medium cursor-pointer flex items-center justify-between spring-transition select-none
                      ${isSelected ? 'bg-purple-600/30 text-white font-semibold' : 'text-gray-300 hover:bg-white/[0.05] hover:text-white'}
                    `}
                  >
                    <span className="truncate">{ws.name}</span>
                    {isSelected && <Check className="w-3.5 h-3.5 text-purple-400" />}
                  </div>
                );
              })
            ) : (
              <div className="p-3 text-center text-xs text-gray-500">
                No matching workspaces.
              </div>
            )}
          </div>

          <div className="pt-2 border-t border-white/10 flex items-center justify-between text-[11px] px-1">
            <button
              type="button"
              onClick={() => {
                setOpen(false);
                router.visit('/workspaces');
              }}
              className="text-purple-300 hover:text-white flex items-center gap-1 font-medium cursor-pointer"
            >
              <Plus className="w-3 h-3" /> Manage Workspaces
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
