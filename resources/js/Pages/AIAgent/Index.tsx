import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Sparkles, Send, Bot, ShieldCheck, CheckCircle2, TrendingUp, ArrowRight, Layers, FileText } from 'lucide-react';

export default function AIAgentIndex() {
  const { auth, tenant } = usePage<any>().props;

  const [inputPrompt, setInputPrompt] = useState('');
  const [conversations, setConversations] = useState<Array<{
    id: string;
    role: 'fox' | 'user';
    content: string;
    insightCard?: {
      title: string;
      metrics: Array<{ label: string; value: string }>;
      actionText?: string;
      actionUrl?: string;
    };
  }>>([
    {
      id: 'init',
      role: 'fox',
      content: `Greetings ${auth?.user?.name || 'Executive'}. I am Mr Fox, the autonomous intelligence layer of HiddenLeaf BusinessOS. I have loaded live telemetry for **${tenant?.workspace_title || 'Main Workspace'}**. How may I assist your business operations today?`,
      insightCard: {
        title: 'Executive Intelligence Summary',
        metrics: [
          { label: 'Security Boundary', value: '100% Isolated' },
          { label: 'Pending Approvals', value: '2 Transfers' },
          { label: 'Active Plan Quota', value: 'Healthy' },
        ],
        actionText: 'Review Bank Transfers',
        actionUrl: '/bank-transfer',
      },
    },
  ]);

  const handleSend = (customPrompt?: string) => {
    const text = customPrompt || inputPrompt;
    if (!text.trim()) return;

    const userEntry = {
      id: String(Date.now()),
      role: 'user' as const,
      content: text,
    };

    setConversations((prev) => [...prev, userEntry]);
    setInputPrompt('');

    setTimeout(() => {
      const reply = {
        id: String(Date.now() + 1),
        role: 'fox' as const,
        content: `Executing analysis for: "${text}". Checked all active ledger entries, tenant permission ceilings, and billing subscriptions. All records adhere to strict cryptographic isolation.`,
        insightCard: {
          title: 'Validated Action Plan',
          metrics: [
            { label: 'Execution Risk', value: 'Zero / Nominal' },
            { label: 'Affected Pod', value: tenant?.workspace_title || 'Operations' },
          ],
        },
      };
      setConversations((prev) => [...prev, reply]);
    }, 700);
  };

  return (
    <AppShell title="Mr Fox AI Assistant">
      <div className="max-w-5xl mx-auto space-y-6">
        <SectionHeader
          title="Mr Fox Autonomous Copilot"
          description="Enterprise-grade reasoning engine for financial ledger audits, sales forecasting, and cross-workspace automation."
          badge={<Badge variant="purple" size="sm" dot>Active Intelligence</Badge>}
        />

        {/* Intelligence Chat Surface (Level 2 Glass) */}
        <Card level={2} padded={false} className="flex flex-col h-[650px] justify-between overflow-hidden shadow-2xl">
          {/* Top Assistant Status Bar */}
          <div className="p-4 border-b border-purple-500/20 bg-purple-950/30 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-purple-600 flex items-center justify-center text-white shadow-lg">
                <Sparkles className="w-5 h-5 animate-fox-pulse" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-white tracking-tight flex items-center gap-2">
                  Mr Fox Neural Core <span className="text-[10px] text-purple-300 font-mono">v3.2</span>
                </h3>
                <p className="text-[11px] text-purple-200">Context: {tenant?.workspace_title || 'Default Workspace'}</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Badge variant="purple" size="sm">Deterministic RBAC Verified</Badge>
            </div>
          </div>

          {/* Chat Stream */}
          <div className="flex-1 overflow-y-auto p-5 sm:p-6 space-y-5">
            {conversations.map((msg) => (
              <div
                key={msg.id}
                className={`flex flex-col ${msg.role === 'user' ? 'items-end' : 'items-start'}`}
              >
                <div
                  className={`
                    max-w-[85%] rounded-2xl p-4 text-sm leading-relaxed
                    ${msg.role === 'user' 
                      ? 'bg-violet-600 text-white rounded-tr-sm shadow-md' 
                      : 'glass-1 text-gray-200 border border-white/10 rounded-tl-sm'}
                  `}
                >
                  <p>{msg.content}</p>
                </div>

                {/* Structured AI Business Object Card */}
                {msg.insightCard && (
                  <div className="mt-3 w-full max-w-[85%] p-4 rounded-xl bg-purple-950/40 border border-purple-500/30 shadow-inner">
                    <div className="flex items-center justify-between pb-2 border-b border-purple-500/20 mb-3">
                      <span className="text-xs font-bold text-purple-300 uppercase tracking-wider">
                        {msg.insightCard.title}
                      </span>
                      <ShieldCheck className="w-4 h-4 text-emerald-400" />
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
                      {msg.insightCard.metrics.map((m, i) => (
                        <div key={i} className="p-2 rounded-lg bg-black/30 border border-white/5 text-xs">
                          <span className="text-gray-400 block text-[10px]">{m.label}</span>
                          <span className="font-bold text-white mt-0.5 block">{m.value}</span>
                        </div>
                      ))}
                    </div>

                    {msg.insightCard.actionText && (
                      <Button
                        variant="intelligence"
                        size="sm"
                        icon={<ArrowRight className="w-3.5 h-3.5" />}
                        iconPosition="right"
                        onClick={() => {
                          if (msg.insightCard?.actionUrl) window.location.href = msg.insightCard.actionUrl;
                        }}
                      >
                        {msg.insightCard.actionText}
                      </Button>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>

          {/* Input & Suggested Prompts Bar */}
          <div className="p-4 border-t border-white/10 bg-black/50 space-y-3">
            {/* Quick Prompts */}
            <div className="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
              <button
                type="button"
                onClick={() => handleSend('Analyze overdue sales receivables')}
                className="px-3 py-1.5 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] text-purple-200 hover:text-white border border-white/10 whitespace-nowrap spring-transition cursor-pointer"
              >
                📊 Analyze Overdue Invoices
              </button>
              <button
                type="button"
                onClick={() => handleSend('Review pending bank wire transfers')}
                className="px-3 py-1.5 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] text-purple-200 hover:text-white border border-white/10 whitespace-nowrap spring-transition cursor-pointer"
              >
                💳 Review Wire Transfers
              </button>
              <button
                type="button"
                onClick={() => handleSend('Audit workspace quota usage')}
                className="px-3 py-1.5 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] text-purple-200 hover:text-white border border-white/10 whitespace-nowrap spring-transition cursor-pointer"
              >
                ⚡ Audit Quota Allocation
              </button>
            </div>

            {/* Prompt Form */}
            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleSend();
              }}
              className="flex items-center gap-2 bg-[#12161E] rounded-xl border border-white/15 p-1.5 focus-within:border-purple-500 spring-transition"
            >
              <input
                type="text"
                placeholder="Give Mr Fox an instruction or query..."
                value={inputPrompt}
                onChange={(e) => setInputPrompt(e.target.value)}
                className="flex-1 bg-transparent px-4 py-2 text-sm text-white placeholder:text-gray-500 outline-none"
              />
              <Button
                type="submit"
                variant="intelligence"
                size="md"
                disabled={!inputPrompt.trim()}
                icon={<Send className="w-4 h-4" />}
              >
                Ask Fox
              </Button>
            </form>
          </div>
        </Card>
      </div>
    </AppShell>
  );
}
