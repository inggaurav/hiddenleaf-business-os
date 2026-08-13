import React, { useState } from 'react';
import { 
  X, 
  Sparkles, 
  Send, 
  CheckCircle, 
  AlertTriangle, 
  ArrowRight, 
  FileText, 
  DollarSign, 
  Bot,
  TrendingUp,
  Clock
} from 'lucide-react';
import { Button } from '../UI/Button';
import { IconButton } from '../UI/IconButton';
import { Badge } from '../UI/Badge';

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
  insight?: {
    type: 'recommendation' | 'alert' | 'opportunity';
    title: string;
    description: string;
    actionLabel?: string;
    actionUrl?: string;
  };
  actions?: Array<{
    label: string;
    action: () => void;
  }>;
}

export const MrFoxPanel: React.FC<MrFoxPanelProps> = ({
  isOpen,
  onClose,
  initialPrompt = '',
  contextPage = 'Dashboard',
}) => {
  const [prompt, setPrompt] = useState(initialPrompt);
  const [messages, setMessages] = useState<Message[]>([
    {
      id: '1',
      sender: 'fox',
      text: `Hello! I am Mr Fox, your autonomous BusinessOS intelligence. I am monitoring your **${contextPage}** in real time.`,
      insight: {
        type: 'recommendation',
        title: '2 Bank Transfers Awaiting Review',
        description: 'New subscription bank transfer receipts submitted today totaling $450.00.',
        actionLabel: 'Review Bank Transfers',
        actionUrl: '/bank-transfer',
      },
    },
    {
      id: '2',
      sender: 'fox',
      text: 'Here are quick proactive operations you can perform right now:',
      actions: [
        { label: 'Summarize Today\'s Sales Invoices', action: () => handleSend('Summarize today sales invoices') },
        { label: 'Check Overdue Receivables', action: () => handleSend('Show overdue receivables') },
        { label: 'Audit High Priority Tickets', action: () => handleSend('Check urgent helpdesk tickets') },
      ],
    },
  ]);

  const handleSend = (textToSend?: string) => {
    const text = textToSend || prompt;
    if (!text.trim()) return;

    const userMsg: Message = {
      id: String(Date.now()),
      sender: 'user',
      text,
    };

    setMessages((prev) => [...prev, userMsg]);
    setPrompt('');

    // Dynamic simulated Mr Fox response with structured action card
    setTimeout(() => {
      const foxReply: Message = {
        id: String(Date.now() + 1),
        sender: 'fox',
        text: `Analysis complete for "${text}". All verified ledger and multi-tenant parameters pass policy requirements.`,
        insight: {
          type: 'opportunity',
          title: 'Automated Recommendation',
          description: 'No anomalies detected across tenant workspace boundaries.',
          actionLabel: 'View Detailed Report',
        },
      };
      setMessages((prev) => [...prev, foxReply]);
    }, 600);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/60 backdrop-blur-sm spring-transition animate-in fade-in duration-200"
        onClick={onClose}
      />

      {/* Floating Right Sheet Surface */}
      <div className="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10">
        <div className="w-screen max-w-md sm:max-w-lg glass-2 border-l border-purple-500/20 shadow-2xl flex flex-col justify-between overflow-hidden animate-in slide-in-from-right duration-300 spring-transition">
          {/* Header */}
          <div className="p-4 sm:p-5 border-b border-white/10 bg-purple-950/30 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-purple-600 flex items-center justify-center text-white shadow-md">
                <Sparkles className="w-5 h-5 animate-fox-pulse" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h3 className="text-base font-bold text-white tracking-tight">Mr Fox Intelligence</h3>
                  <Badge variant="purple" size="sm" dot>Live</Badge>
                </div>
                <p className="text-[11px] text-purple-300">Context: {contextPage}</p>
              </div>
            </div>

            <IconButton label="Close Mr Fox" size="sm" onClick={onClose}>
              <X className="w-4 h-4" />
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
                    max-w-[85%] rounded-2xl p-3.5 text-sm leading-relaxed
                    ${msg.sender === 'user' 
                      ? 'bg-violet-600 text-white rounded-tr-sm shadow-md' 
                      : 'glass-1 text-gray-200 border border-white/10 rounded-tl-sm'}
                  `}
                >
                  <p>{msg.text}</p>
                </div>

                {/* Structured Insight Card */}
                {msg.insight && (
                  <div className="mt-2.5 w-full max-w-[95%] p-3.5 rounded-xl bg-purple-900/20 border border-purple-500/30 shadow-inner">
                    <div className="flex items-center gap-2 text-xs font-semibold text-purple-300 mb-1">
                      {msg.insight.type === 'alert' ? (
                        <AlertTriangle className="w-4 h-4 text-amber-400" />
                      ) : (
                        <TrendingUp className="w-4 h-4 text-emerald-400" />
                      )}
                      <span>{msg.insight.title}</span>
                    </div>
                    <p className="text-xs text-gray-300 mb-3">{msg.insight.description}</p>
                    {msg.insight.actionLabel && (
                      <Button
                        variant="intelligence"
                        size="sm"
                        icon={<ArrowRight className="w-3.5 h-3.5" />}
                        iconPosition="right"
                        onClick={() => {
                          if (msg.insight?.actionUrl) window.location.href = msg.insight.actionUrl;
                        }}
                      >
                        {msg.insight.actionLabel}
                      </Button>
                    )}
                  </div>
                )}

                {/* Quick Action Buttons */}
                {msg.actions && (
                  <div className="mt-2.5 flex flex-col gap-1.5 w-full max-w-[95%]">
                    {msg.actions.map((act, i) => (
                      <button
                        key={i}
                        onClick={act.action}
                        className="text-left text-xs px-3 py-2 rounded-lg bg-white/[0.05] hover:bg-white/[0.1] text-purple-200 hover:text-white border border-white/10 spring-transition cursor-pointer"
                      >
                        ⚡ {act.label}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>

          {/* Prompt Input Box */}
          <div className="p-4 border-t border-white/10 bg-black/40">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleSend();
              }}
              className="flex items-center gap-2 bg-[#12161E] rounded-xl border border-white/15 p-1.5 focus-within:border-purple-500 spring-transition"
            >
              <input
                type="text"
                placeholder="Ask Mr Fox anything or give an instruction..."
                value={prompt}
                onChange={(e) => setPrompt(e.target.value)}
                className="flex-1 bg-transparent px-3 py-1.5 text-sm text-white placeholder:text-gray-500 outline-none"
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
            <p className="text-[10px] text-gray-400 text-center mt-2">
              Mr Fox uses deterministic multi-tenant security verification before executing actions.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
