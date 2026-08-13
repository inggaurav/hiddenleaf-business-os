import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { 
  Search, 
  LayoutDashboard, 
  Layers, 
  FileText, 
  CreditCard, 
  Users, 
  Settings, 
  Headphones, 
  Image, 
  MessageSquare, 
  Sparkles, 
  Plus, 
  ArrowRight,
  ShieldCheck,
  Building,
  DollarSign
} from 'lucide-react';

interface CommandPaletteProps {
  isOpen: boolean;
  onClose: () => void;
  onOpenMrFox?: (prompt?: string) => void;
}

interface CommandItem {
  id: string;
  title: string;
  category: 'Navigation' | 'Actions' | 'Mr Fox Intelligence';
  icon: React.ReactNode;
  action: () => void;
  shortcut?: string;
}

export const CommandPalette: React.FC<CommandPaletteProps> = ({
  isOpen,
  onClose,
  onOpenMrFox,
}) => {
  const [query, setQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);

  const commands: CommandItem[] = [
    // Navigation
    {
      id: 'nav-dash',
      title: 'Go to Executive Dashboard',
      category: 'Navigation',
      icon: <LayoutDashboard className="w-4 h-4 text-violet-400" />,
      action: () => router.visit('/dashboard'),
    },
    {
      id: 'nav-invoices',
      title: 'Sales Invoices & Billing',
      category: 'Navigation',
      icon: <FileText className="w-4 h-4 text-emerald-400" />,
      action: () => router.visit('/sales-invoices'),
    },
    {
      id: 'nav-proposals',
      title: 'Sales Proposals & Quotes',
      category: 'Navigation',
      icon: <FileText className="w-4 h-4 text-cyan-400" />,
      action: () => router.visit('/sales-proposals'),
    },
    {
      id: 'nav-warehouses',
      title: 'Warehouses & Stock Transfers',
      category: 'Navigation',
      icon: <Building className="w-4 h-4 text-amber-400" />,
      action: () => router.visit('/warehouses'),
    },
    {
      id: 'nav-plans',
      title: 'SaaS Plans & Subscription Tiers',
      category: 'Navigation',
      icon: <CreditCard className="w-4 h-4 text-purple-400" />,
      action: () => router.visit('/plans'),
    },
    {
      id: 'nav-transfers',
      title: 'Bank Transfer Approvals',
      category: 'Navigation',
      icon: <DollarSign className="w-4 h-4 text-emerald-400" />,
      action: () => router.visit('/bank-transfer'),
    },
    {
      id: 'nav-helpdesk',
      title: 'Helpdesk & Support Tickets',
      category: 'Navigation',
      icon: <Headphones className="w-4 h-4 text-rose-400" />,
      action: () => router.visit('/helpdesk-tickets'),
    },
    {
      id: 'nav-media',
      title: 'Media Library & Assets',
      category: 'Navigation',
      icon: <Image className="w-4 h-4 text-blue-400" />,
      action: () => router.visit('/media/page'),
    },
    {
      id: 'nav-chat',
      title: 'Team Messenger & Chat',
      category: 'Navigation',
      icon: <MessageSquare className="w-4 h-4 text-indigo-400" />,
      action: () => router.visit('/chats'),
    },
    {
      id: 'nav-roles',
      title: 'Roles & RBAC Permissions',
      category: 'Navigation',
      icon: <ShieldCheck className="w-4 h-4 text-amber-400" />,
      action: () => router.visit('/roles'),
    },
    {
      id: 'nav-workspaces',
      title: 'Workspace Management',
      category: 'Navigation',
      icon: <Layers className="w-4 h-4 text-purple-400" />,
      action: () => router.visit('/workspaces'),
    },
    {
      id: 'nav-settings',
      title: 'System & Workspace Settings',
      category: 'Navigation',
      icon: <Settings className="w-4 h-4 text-gray-400" />,
      action: () => router.visit('/settings'),
    },

    // Quick Actions
    {
      id: 'act-invoice',
      title: 'Create New Sales Invoice',
      category: 'Actions',
      icon: <Plus className="w-4 h-4 text-emerald-400" />,
      action: () => router.visit('/sales-invoices/create'),
    },
    {
      id: 'act-proposal',
      title: 'Create New Proposal',
      category: 'Actions',
      icon: <Plus className="w-4 h-4 text-cyan-400" />,
      action: () => router.visit('/sales-proposals/create'),
    },
    {
      id: 'act-ticket',
      title: 'Open Support Ticket',
      category: 'Actions',
      icon: <Plus className="w-4 h-4 text-rose-400" />,
      action: () => router.visit('/helpdesk-tickets/create'),
    },
    {
      id: 'act-user',
      title: 'Invite Team Member',
      category: 'Actions',
      icon: <Plus className="w-4 h-4 text-violet-400" />,
      action: () => router.visit('/users/create'),
    },

    // Mr Fox Prompt
    {
      id: 'fox-ask',
      title: query ? `Ask Mr Fox: "${query}"` : 'Ask Mr Fox for business insights',
      category: 'Mr Fox Intelligence',
      icon: <Sparkles className="w-4 h-4 text-amber-400" />,
      action: () => {
        if (onOpenMrFox) onOpenMrFox(query);
      },
    },
  ];

  const filteredCommands = commands.filter((cmd) => {
    if (cmd.category === 'Mr Fox Intelligence') return true;
    return cmd.title.toLowerCase().includes(query.toLowerCase());
  });

  useEffect(() => {
    setSelectedIndex(0);
  }, [query]);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (!isOpen) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setSelectedIndex((prev) => (prev + 1) % filteredCommands.length);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setSelectedIndex((prev) => (prev - 1 + filteredCommands.length) % filteredCommands.length);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (filteredCommands[selectedIndex]) {
          filteredCommands[selectedIndex].action();
          onClose();
        }
      } else if (e.key === 'Escape') {
        onClose();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, selectedIndex, filteredCommands, onClose]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-16 sm:pt-24 p-4">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/80 backdrop-blur-md spring-transition animate-in fade-in duration-150"
        onClick={onClose}
      />

      {/* Command Palette Surface */}
      <div className="relative w-full max-w-xl glass-dropdown rounded-2xl border border-white/20 shadow-2xl overflow-hidden z-10 animate-in zoom-in-95 duration-150">
        {/* Search Header */}
        <div className="flex items-center px-4 py-3.5 border-b border-white/10 gap-3">
          <Search className="w-5 h-5 text-gray-400 flex-shrink-0" />
          <input
            autoFocus
            type="text"
            placeholder="Type a command, search pages, or ask Mr Fox..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="w-full bg-transparent text-white text-sm outline-none placeholder:text-gray-500"
          />
          <kbd className="hidden sm:inline-block text-[10px] uppercase font-semibold px-2 py-0.5 rounded bg-white/10 text-gray-400 border border-white/10">
            ESC
          </kbd>
        </div>

        {/* Command List */}
        <div className="max-h-80 overflow-y-auto p-2 divide-y divide-white/[0.04]">
          {filteredCommands.map((cmd, idx) => {
            const isSelected = selectedIndex === idx;
            return (
              <div
                key={cmd.id}
                onClick={() => {
                  cmd.action();
                  onClose();
                }}
                onMouseEnter={() => setSelectedIndex(idx)}
                className={`
                  flex items-center justify-between px-3 py-2.5 rounded-lg cursor-pointer spring-transition text-sm
                  ${isSelected ? 'bg-violet-600/30 text-white border border-violet-500/30' : 'text-gray-300 hover:bg-white/[0.04]'}
                `}
              >
                <div className="flex items-center gap-3">
                  <div className="p-1 rounded bg-white/[0.05] border border-white/10">
                    {cmd.icon}
                  </div>
                  <span className="font-medium">{cmd.title}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-[11px] text-gray-500">{cmd.category}</span>
                  {isSelected && <ArrowRight className="w-3.5 h-3.5 text-violet-400" />}
                </div>
              </div>
            );
          })}
        </div>

        {/* Footer */}
        <div className="px-4 py-2.5 border-t border-white/10 bg-white/[0.02] flex items-center justify-between text-xs text-gray-400">
          <div className="flex items-center gap-2">
            <span>Navigation: <kbd className="px-1.5 py-0.5 bg-white/10 rounded">↑</kbd> <kbd className="px-1.5 py-0.5 bg-white/10 rounded">↓</kbd></span>
            <span>Select: <kbd className="px-1.5 py-0.5 bg-white/10 rounded">↵</kbd></span>
          </div>
          <span className="text-purple-400 flex items-center gap-1 font-medium">
            <Sparkles className="w-3 h-3" /> Mr Fox Ready
          </span>
        </div>
      </div>
    </div>
  );
};
