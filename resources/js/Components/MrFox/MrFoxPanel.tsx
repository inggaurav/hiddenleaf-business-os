import React, { useState, useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { 
  X, 
  Send, 
  ArrowRight, 
  FileText, 
  DollarSign, 
  Headphones, 
  Settings,
  Sparkles,
  CheckCircle2,
  AlertTriangle,
  ExternalLink,
  ShieldCheck,
  Check,
  Ban,
  Clock,
  Layers
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

interface EvidenceItem {
  type: string;
  id?: number | string;
  label: string;
  route?: string;
  amount?: number;
  stock?: number;
}

interface ActionProposal {
  proposal_id: number;
  tool_name: string;
  human_summary: string;
  risk_level: string;
  status: string;
}

interface ToolExecution {
  tool: string;
  success: boolean;
  summary: string;
  requires_approval?: boolean;
  proposal_id?: number;
}

interface Message {
  id: string;
  sender: 'fox' | 'user';
  text: string;
  tools?: ToolExecution[];
  proposals?: ActionProposal[];
  evidence?: EvidenceItem[];
  provider?: string;
  model?: string;
  createdAt?: string;
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
  const [sessionId, setSessionId] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    {
      id: 'welcome',
      sender: 'fox',
      text: `Hello! I am Mr. Fox, your executive intelligence layer. I can analyze business metrics, inspect invoices, monitor inventory stock levels, check active CRM pipelines, and propose authorized actions for **${contextPage}**.`,
    },
  ]);

  const triggerRef = useRef<HTMLElement | null>(null);
  const messagesEndRef = useRef<HTMLDivElement | null>(null);

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

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, loading]);

  const authorizedShortcuts = FOX_SHORTCUTS.filter((sc) =>
    isItemAuthorized(sc, user, isSuperAdmin, userPermissions, enabledModules)
  );

  const handleSend = async (textToSend?: string) => {
    const text = textToSend || prompt;
    if (!text.trim() || loading) return;

    const userMsg: Message = {
      id: String(Date.now()),
      sender: 'user',
      text,
      createdAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    };

    setMessages((prev) => [...prev, userMsg]);
    setPrompt('');
    setLoading(true);

    try {
      const res = await fetch('/api/v1/mr-fox/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie
              .split('; ')
              .find((row) => row.startsWith('XSRF-TOKEN='))
              ?.split('=')[1] || ''
          ),
        },
        body: JSON.stringify({
          message: text,
          session_id: sessionId,
          active_page: contextPage,
        }),
      });

      if (!res.ok) {
        throw new Error(`HTTP Error ${res.status}`);
      }

      const data = await res.json();
      if (data.session_id) {
        setSessionId(data.session_id);
      }

      const foxReply: Message = {
        id: String(Date.now() + 1),
        sender: 'fox',
        text: data.reply || 'Analysis completed.',
        tools: data.tools_executed || [],
        proposals: data.action_proposals || [],
        evidence: data.evidence || [],
        provider: data.provider,
        model: data.model,
        createdAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      };

      setMessages((prev) => [...prev, foxReply]);
    } catch (err: any) {
      const errorReply: Message = {
        id: String(Date.now() + 1),
        sender: 'fox',
        text: `Unable to complete AI request: ${err.message || 'Service unavailable'}. Please verify AI provider settings.`,
        createdAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      };
      setMessages((prev) => [...prev, errorReply]);
    } finally {
      setLoading(false);
    }
  };

  const handleApproveProposal = async (proposalId: number) => {
    try {
      const res = await fetch(`/api/v1/mr-fox/actions/${proposalId}/approve`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie
              .split('; ')
              .find((row) => row.startsWith('XSRF-TOKEN='))
              ?.split('=')[1] || ''
          ),
        },
      });

      const data = await res.json();
      if (res.ok) {
        setMessages((prev) => [
          ...prev,
          {
            id: String(Date.now()),
            sender: 'fox',
            text: `Action Approved & Executed: ${data.summary}`,
            evidence: data.evidence || [],
          },
        ]);
      } else {
        alert(data.error || 'Failed to approve action.');
      }
    } catch (e: any) {
      alert(`Approval error: ${e.message}`);
    }
  };

  const handleRejectProposal = async (proposalId: number) => {
    try {
      const res = await fetch(`/api/v1/mr-fox/actions/${proposalId}/reject`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie
              .split('; ')
              .find((row) => row.startsWith('XSRF-TOKEN='))
              ?.split('=')[1] || ''
          ),
        },
      });

      if (res.ok) {
        setMessages((prev) => [
          ...prev,
          {
            id: String(Date.now()),
            sender: 'fox',
            text: `Action proposal #${proposalId} rejected and cancelled.`,
          },
        ]);
      }
    } catch (e: any) {
      alert(`Rejection error: ${e.message}`);
    }
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
        <div className="w-screen max-w-md sm:max-w-xl glass-2 border-l border-[var(--border-medium)] shadow-2xl flex flex-col justify-between overflow-hidden spring-transition bg-[var(--surface-1)]">
          {/* Header */}
          <div className="p-4 sm:p-5 border-b border-[var(--border-subtle)] bg-purple-950/20 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-purple-900/30 border border-purple-500/30 flex items-center justify-center shadow-md">
                <MrFoxMark size={22} />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h2 id="mrfox-panel-title" className="text-sm sm:text-base font-bold text-[var(--text-primary)] tracking-tight">
                    Mr. Fox Intelligence
                  </h2>
                  <Badge variant="purple" size="sm">Active Operator</Badge>
                </div>
                <p className="text-[11px] text-[var(--text-tertiary)]">Page Context: {contextPage}</p>
              </div>
            </div>

            <IconButton label="Close Mr Fox" size="sm" onClick={onClose}>
              <X className="w-4 h-4 text-[var(--text-secondary)]" />
            </IconButton>
          </div>

          {/* Quick Prompts Bar */}
          <div className="px-4 py-2 bg-[var(--surface-2)]/60 border-b border-[var(--border-subtle)] flex items-center gap-1.5 overflow-x-auto text-[11px]">
            <button
              onClick={() => handleSend("Summarize today's business performance and KPIs")}
              className="px-2.5 py-1 rounded-lg bg-[var(--surface-3)] hover:bg-purple-900/30 border border-[var(--border-subtle)] text-[var(--text-secondary)] hover:text-purple-300 whitespace-nowrap transition-colors"
            >
              📊 Executive Summary
            </button>
            <button
              onClick={() => handleSend("Check overdue invoices and receivables")}
              className="px-2.5 py-1 rounded-lg bg-[var(--surface-3)] hover:bg-purple-900/30 border border-[var(--border-subtle)] text-[var(--text-secondary)] hover:text-purple-300 whitespace-nowrap transition-colors"
            >
              💰 Receivables
            </button>
            <button
              onClick={() => handleSend("Identify low stock products that need reordering")}
              className="px-2.5 py-1 rounded-lg bg-[var(--surface-3)] hover:bg-purple-900/30 border border-[var(--border-subtle)] text-[var(--text-secondary)] hover:text-purple-300 whitespace-nowrap transition-colors"
            >
              📦 Low Stock
            </button>
            <button
              onClick={() => handleSend("Check pending employee leaves and attendance")}
              className="px-2.5 py-1 rounded-lg bg-[var(--surface-3)] hover:bg-purple-900/30 border border-[var(--border-subtle)] text-[var(--text-secondary)] hover:text-purple-300 whitespace-nowrap transition-colors"
            >
              👥 HR Alerts
            </button>
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
                    max-w-[90%] rounded-2xl p-3.5 text-xs sm:text-sm leading-relaxed space-y-2
                    ${msg.sender === 'user' 
                      ? 'bg-purple-600 text-white rounded-tr-sm shadow-md' 
                      : 'glass-1 text-[var(--text-primary)] border border-[var(--border-subtle)] rounded-tl-sm'}
                  `}
                >
                  <p className="whitespace-pre-wrap">{msg.text}</p>

                  {/* Executed Tools Indicators */}
                  {msg.tools && msg.tools.length > 0 && (
                    <div className="pt-2 border-t border-white/10 space-y-1.5">
                      <div className="text-[10px] font-semibold uppercase tracking-wider text-purple-300 flex items-center gap-1">
                        <Sparkles className="w-3 h-3 text-purple-400" />
                        Operational Tools Invoked
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        {msg.tools.map((t, idx) => (
                          <span
                            key={idx}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-950/40 border border-purple-500/30 text-[10px] text-purple-200"
                          >
                            <CheckCircle2 className="w-2.5 h-2.5 text-emerald-400" />
                            {t.tool}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Structured Evidence Chips */}
                  {msg.evidence && msg.evidence.length > 0 && (
                    <div className="pt-2 border-t border-white/10 space-y-1.5">
                      <div className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 flex items-center gap-1">
                        <Layers className="w-3 h-3 text-gray-400" />
                        Grounded ERP Evidence
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        {msg.evidence.map((ev, idx) => {
                          const safeRoute = ev.route && ev.route.startsWith('/') ? ev.route : '#';
                          return (
                            <a
                              key={idx}
                              href={safeRoute}
                              className="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-[var(--surface-3)] hover:bg-purple-900/40 border border-[var(--border-subtle)] text-[11px] text-purple-300 transition-colors"
                            >
                              <span>{ev.label}</span>
                              <ExternalLink className="w-2.5 h-2.5 text-gray-400" />
                            </a>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  {/* Action Proposals for Review & Approval */}
                  {msg.proposals && msg.proposals.length > 0 && (
                    <div className="pt-2 border-t border-white/10 space-y-2">
                      {msg.proposals.map((prop) => (
                        <div
                          key={prop.proposal_id}
                          className="p-3 rounded-xl bg-amber-950/30 border border-amber-500/40 text-xs text-amber-200 space-y-2"
                        >
                          <div className="flex items-center justify-between">
                            <span className="font-semibold flex items-center gap-1 text-amber-400">
                              <ShieldCheck className="w-3.5 h-3.5" />
                              Action Approval Required ({prop.risk_level})
                            </span>
                            <span className="text-[10px] uppercase font-bold text-amber-300">Proposal #{prop.proposal_id}</span>
                          </div>
                          <p className="text-[11px] text-gray-300">{prop.human_summary}</p>
                          <div className="flex items-center gap-2 pt-1">
                            <button
                              onClick={() => handleApproveProposal(prop.proposal_id)}
                              className="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-[11px] flex items-center gap-1 transition-colors"
                            >
                              <Check className="w-3 h-3" /> Approve & Execute
                            </button>
                            <button
                              onClick={() => handleRejectProposal(prop.proposal_id)}
                              className="px-2.5 py-1 rounded-lg bg-rose-900/40 hover:bg-rose-900/60 border border-rose-500/30 text-rose-300 font-medium text-[11px] flex items-center gap-1 transition-colors"
                            >
                              <Ban className="w-3 h-3" /> Reject
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {msg.createdAt && (
                  <span className="text-[10px] text-gray-500 mt-1 px-1">{msg.createdAt}</span>
                )}
              </div>
            ))}

            {loading && (
              <div className="flex items-center gap-2 text-xs text-purple-400 italic">
                <div className="w-2 h-2 rounded-full bg-purple-400 animate-ping" />
                Mr. Fox is analyzing enterprise data and executing tools...
              </div>
            )}

            <div ref={messagesEndRef} />
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
                placeholder="Ask Mr Fox or give an operational instruction..."
                value={prompt}
                onChange={(e) => setPrompt(e.target.value)}
                disabled={loading}
                className="flex-1 bg-transparent px-3 py-1.5 text-xs sm:text-sm text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none"
              />
              <Button
                type="submit"
                variant="intelligence"
                size="sm"
                disabled={!prompt.trim() || loading}
                icon={<Send className="w-3.5 h-3.5" />}
              >
                Send
              </Button>
            </form>
            <p className="text-[10px] text-[var(--text-tertiary)] text-center mt-2">
              Protected by multi-tenant RBAC policies and immutable audit ledger.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
