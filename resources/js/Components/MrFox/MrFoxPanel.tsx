import React, { useState, useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { 
  X, 
  Send, 
  ArrowRight, 
  FileText, 
  DollarSign, 
  Headphones, 
  Settings
} from 'lucide-react';
import { Button } from '../UI/Button';
import { IconButton } from '../UI/IconButton';
import { Badge } from '../UI/Badge';
import { MrFoxMark } from './MrFoxMark';
import { isItemAuthorized, NavigationItem } from '../../Navigation/NavigationRegistry';

interface MrFoxPanelProps {
  isOpen: boolean;
  onClose: () => void;
  initialPrompt?: string;
  contextPage?: string;
}

interface Message {
  id: string;
  sender: 'fox' | 'user';
  text: string;
}

const FOX_SHORTCUTS: NavigationItem[] = [
  {
    id: 'fox-sales-invoices',
    name: 'View Sales Invoices',
    href: '/sales-invoices',
    icon: FileText,
    permission: 'sales.manage',
    category: 'Sales',
  },
  {
    id: 'fox-bank-transfers',
    name: 'Review Bank Transfers',
    href: '/bank-transfer',
    icon: DollarSign,
    permission: 'workspace.view',
    category: 'Billing',
  },
  {
    id: 'fox-helpdesk',
    name: 'Customer Support Tickets',
    href: '/helpdesk-tickets',
    icon: Headphones,
    permission: 'workspace.view',
    category: 'Operations',
  },
  {
    id: 'fox-settings',
    name: 'Configure AI Provider Credentials',
    href: '/settings',
    icon: Settings,
    permission: 'modules.manage',
    category: 'System',
  },
];

export const MrFoxPanel: React.FC<MrFoxPanelProps> = ({
  isOpen,
  onClose,
  initialPrompt = '',
  contextPage = 'Dashboard',
}) => {
  const { auth, tenant } = usePage<any>().props;
  const user = auth?.user;
  const isSuperAdmin = user?.is_super_admin || false;
  const userPermissions: string[] = user?.permissions || [];
  const enabledModules: string[] = tenant?.modules || [];

  const [prompt, setPrompt] = useState(initialPrompt);
  const [messages, setMessages] = useState<Message[]>([
    {
      id: '1',
      sender: 'fox',
      text: `Mr Fox Intelligence service is ready for connection. Configure your AI API credentials in Workspace Settings to enable real-time operational assistance and insights for **${contextPage}**.`,
    },
  ]);

  const triggerRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (isOpen) {
      triggerRef.current = document.activeElement as HTMLElement;
      const mainEl = document.querySelector('main') || document.getElementById('app');
      if (mainEl) {
        mainEl.setAttribute('aria-hidden', 'true');
        (mainEl as any).inert = true;
      }
    } else {
      const mainEl = document.querySelector('main') || document.getElementById('app');
      if (mainEl) {
        mainEl.removeAttribute('aria-hidden');
        (mainEl as any).inert = false;
      }
      triggerRef.current?.focus();
    }
  }, [isOpen]);

  const authorizedShortcuts = FOX_SHORTCUTS.filter((sc) =>
    isItemAuthorized(sc, user, isSuperAdmin, userPermissions, enabledModules)
  );

  const handleSend = (textToSend?: string) => {
    const text = textToSend || prompt;
    if (!text.trim()) return;

    const userMsg: Message = {
      id: String(Date.now()),
      sender: 'user',
      text,
    };

    const reply: Message = {
      id: String(Date.now() + 1),
      sender: 'fox',
      text: `Intelligence service provider is not yet connected. To process "${text}", please configure your AI credentials in Workspace Settings.`,
    };

    setMessages((prev) => [...prev, userMsg, reply]);
    setPrompt('');
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="mrfox-panel-title">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/60 backdrop-blur-sm spring-transition"
        onClick={onClose}
      />

      {/* Floating Right Sheet Surface */}
      <div className="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10">
        <div className="w-screen max-w-md sm:max-w-lg glass-2 border-l border-[var(--border-medium)] shadow-2xl flex flex-col justify-between overflow-hidden spring-transition bg-[var(--surface-1)]">
          {/* Header */}
          <div className="p-4 sm:p-5 border-b border-[var(--border-subtle)] bg-purple-950/20 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-purple-900/30 border border-purple-500/30 flex items-center justify-center shadow-md">
                <MrFoxMark size={22} />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h2 id="mrfox-panel-title" className="text-sm sm:text-base font-bold text-[var(--text-primary)] tracking-tight">
                    Mr Fox Intelligence
                  </h2>
                  <Badge variant="neutral" size="sm">Disconnected / Ready</Badge>
                </div>
                <p className="text-[11px] text-[var(--text-tertiary)]">Context: {contextPage}</p>
              </div>
            </div>

            <IconButton label="Close Mr Fox" size="sm" onClick={onClose}>
              <X className="w-4 h-4 text-[var(--text-secondary)]" />
            </IconButton>
          </div>

          {/* Conversation Thread */}
          <div className="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
            {messages.map((msg) => (
              <div
                key={msg.id}
                className={`flex flex-col ${msg.sender === 'user' ? 'items-end' : 'items-start'}`}
              >
                <div
                  className={`
                    max-w-[85%] rounded-2xl p-3.5 text-xs sm:text-sm leading-relaxed
                    ${msg.sender === 'user' 
                      ? 'bg-purple-600 text-white rounded-tr-sm shadow-md' 
                      : 'glass-1 text-[var(--text-primary)] border border-[var(--border-subtle)] rounded-tl-sm'}
                  `}
                >
                  <p>{msg.text}</p>
                </div>
              </div>
            ))}

            {/* Authorized Suggested Navigation Shortcuts */}
            {authorizedShortcuts.length > 0 && (
              <div className="pt-2">
                <div className="text-[11px] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider mb-2">
                  Operational Shortcuts
                </div>
                <div className="space-y-1.5">
                  {authorizedShortcuts.map((sc) => {
                    const Icon = sc.icon;
                    return (
                      <a
                        key={sc.id}
                        href={sc.href}
                        className="flex items-center justify-between p-2.5 rounded-xl bg-[var(--surface-2)] hover:bg-white/[0.05] border border-[var(--border-subtle)] text-xs text-[var(--text-primary)] spring-transition"
                      >
                        <div className="flex items-center gap-2">
                          <Icon className="w-3.5 h-3.5 text-purple-400" />
                          <span>{sc.name}</span>
                        </div>
                        <ArrowRight className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
                      </a>
                    );
                  })}
                </div>
              </div>
            )}
          </div>

          {/* Prompt Input Box */}
          <div className="p-4 border-t border-[var(--border-subtle)] bg-[var(--surface-1)]">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleSend();
              }}
              className="flex items-center gap-2 bg-[var(--surface-2)] rounded-xl border border-[var(--border-medium)] p-1.5 focus-within:border-purple-500 spring-transition"
            >
              <input
                type="text"
                placeholder="Ask Mr Fox or enter an operational instruction..."
                value={prompt}
                onChange={(e) => setPrompt(e.target.value)}
                className="flex-1 bg-transparent px-3 py-1.5 text-xs sm:text-sm text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none"
              />
              <Button
                type="submit"
                variant="intelligence"
                size="sm"
                disabled={!prompt.trim()}
                icon={<Send className="w-3.5 h-3.5" />}
              >
                Send
              </Button>
            </form>
            <p className="text-[10px] text-[var(--text-tertiary)] text-center mt-2">
              Connect an AI provider in Workspace Settings to enable live reasoning.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
