import React, { useState } from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { MrFoxMark } from '@/Components/MrFox/MrFoxMark';
import { MrFoxOrb } from '@/Components/MrFox/MrFoxOrb';
import { 
  Bot, 
  Sparkles, 
  Send, 
  Terminal, 
  Key, 
  Settings, 
  ArrowRight,
  ShieldCheck,
  Cpu
} from 'lucide-react';

export default function AIAgentIndex() {
  const { auth, tenant } = usePage<any>().props;
  const [prompt, setPrompt] = useState('');
  const [messages, setMessages] = useState<Array<{ id: string; sender: 'fox' | 'user'; text: string }>>([
    {
      id: 'welcome',
      sender: 'fox',
      text: 'Mr Fox Intelligence service is ready for provider integration. Configure your AI model API key in Workspace Settings to enable real-time operational reasoning and automated enterprise actions.',
    },
  ]);

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!prompt.trim()) return;

    const userMsg = {
      id: String(Date.now()),
      sender: 'user' as const,
      text: prompt,
    };

    const replyMsg = {
      id: String(Date.now() + 1),
      sender: 'fox' as const,
      text: `Intelligence service provider is not yet connected. To process "${prompt}", please configure your AI API credentials in Settings.`,
    };

    setMessages((prev) => [...prev, userMsg, replyMsg]);
    setPrompt('');
  };

  return (
    <AppShell title="Mr Fox AI Assistant">
      <div className="space-y-6">
        <SectionHeader
          title="Mr Fox Intelligence Platform"
          description="Autonomous enterprise reasoning, natural language querying, and cross-workspace automation."
          badge={<Badge variant="purple" size="sm">Neural Assistant</Badge>}
          actions={
            <Link href="/settings">
              <Button variant="secondary" size="sm" icon={<Settings className="w-3.5 h-3.5" />}>
                Configure AI Provider
              </Button>
            </Link>
          }
        />

        {/* Intelligence Status Banner */}
        <Card level={1} className="flex flex-col sm:flex-row items-center justify-between gap-6 border-purple-500/20 bg-purple-950/10">
          <div className="flex items-center gap-4">
            <MrFoxOrb size="md" />
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <h2 className="text-base font-bold text-[var(--text-primary)]">
                  Mr Fox Reasoning Engine
                </h2>
                <Badge variant="neutral" size="sm">Disconnected / Ready</Badge>
              </div>
              <p className="text-xs text-[var(--text-secondary)]">
                Ready to link with OpenAI, Anthropic, or custom private LLM endpoints.
              </p>
            </div>
          </div>

          <Link href="/settings">
            <Button variant="intelligence" size="sm" icon={<Key className="w-3.5 h-3.5" />}>
              Add API Credentials
            </Button>
          </Link>
        </Card>

        {/* Interactive Chat Console */}
        <Card level={0} padded={false} className="overflow-hidden flex flex-col h-[500px]">
          {/* Console Header */}
          <div className="p-3.5 border-b border-[var(--border-subtle)] bg-[var(--surface-2)] flex items-center justify-between">
            <div className="flex items-center gap-2 text-xs font-semibold text-[var(--text-secondary)]">
              <Terminal className="w-4 h-4 text-purple-400" />
              <span>Interactive Session</span>
            </div>
            <span className="text-[11px] text-[var(--text-tertiary)] font-mono">
              Model: None Connected
            </span>
          </div>

          {/* Messages Stream */}
          <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
            {messages.map((msg) => (
              <div
                key={msg.id}
                className={`flex flex-col ${msg.sender === 'user' ? 'items-end' : 'items-start'}`}
              >
                <div
                  className={`
                    max-w-[80%] rounded-2xl p-4 text-xs sm:text-sm leading-relaxed
                    ${msg.sender === 'user'
                      ? 'bg-purple-600 text-white rounded-tr-sm shadow-md'
                      : 'glass-1 text-[var(--text-primary)] border border-[var(--border-subtle)] rounded-tl-sm'}
                  `}
                >
                  <p>{msg.text}</p>
                </div>
              </div>
            ))}
          </div>

          {/* Prompt Form */}
          <div className="p-3 border-t border-[var(--border-subtle)] bg-[var(--surface-1)]">
            <form onSubmit={handleSend} className="flex items-center gap-2">
              <input
                type="text"
                value={prompt}
                onChange={(e) => setPrompt(e.target.value)}
                placeholder="Ask Mr Fox to analyze data or automate an action..."
                className="flex-1 bg-[var(--surface-2)] border border-[var(--border-subtle)] focus:border-purple-500 rounded-xl px-4 py-2 text-xs sm:text-sm text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none"
              />
              <Button
                type="submit"
                variant="intelligence"
                size="md"
                disabled={!prompt.trim()}
                icon={<Send className="w-4 h-4" />}
              >
                Send
              </Button>
            </form>
          </div>
        </Card>
      </div>
    </AppShell>
  );
}
