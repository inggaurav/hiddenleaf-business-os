import React, { useState } from 'react';
import { usePage, useForm } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { MessageSquare, Send, Search, Users, Circle, Sparkles } from 'lucide-react';

export default function MessengerIndex() {
  const { chats = [], contacts = [], auth } = usePage<any>().props;

  const [activeContact, setActiveContact] = useState<any>(contacts[0] || { id: 1, name: 'Team Channel', email: 'general@workspace' });
  const [messages, setMessages] = useState<Array<{ id: number; sender_id: number; text: string; time: string }>>([
    { id: 1, sender_id: 999, text: 'Welcome to the unified workspace channel. Real-time updates and tenant logs stream here.', time: '09:00 AM' },
    { id: 2, sender_id: auth?.user?.id || 1, text: 'Checked current multi-tenant boundaries and active order ledgers. Everything is nominal.', time: '09:15 AM' },
  ]);

  const [inputText, setInputText] = useState('');

  const handleSendMessage = (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputText.trim()) return;

    setMessages((prev) => [
      ...prev,
      {
        id: Date.now(),
        sender_id: auth?.user?.id || 1,
        text: inputText,
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      },
    ]);
    setInputText('');
  };

  return (
    <AppShell title="Team Messenger">
      <div className="space-y-6">
        <SectionHeader
          title="Real-Time Team Messenger"
          description="Instant cross-workspace messaging, peer communications, and operational alert broadcasting."
          badge={<Badge variant="purple" size="sm" dot>Connected</Badge>}
        />

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 h-[600px]">
          {/* Contacts & Channels Sidebar */}
          <Card level={0} padded={false} className="flex flex-col h-full overflow-hidden">
            <div className="p-3.5 border-b border-white/10">
              <Input
                placeholder="Search conversations..."
                leftIcon={<Search className="w-3.5 h-3.5 text-gray-400" />}
              />
            </div>

            <div className="flex-1 overflow-y-auto divide-y divide-white/[0.04] p-2">
              <div
                onClick={() => setActiveContact({ id: 1, name: 'Team Channel', email: 'general@workspace' })}
                className={`
                  p-3 rounded-lg flex items-center gap-3 cursor-pointer spring-transition select-none
                  ${activeContact?.id === 1 ? 'bg-violet-600/20 border border-violet-500/30' : 'hover:bg-white/[0.03]'}
                `}
              >
                <div className="w-9 h-9 rounded-xl bg-violet-600 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                  #
                </div>
                <div className="truncate flex-1">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-white truncate">General Workspace</span>
                    <span className="text-[10px] text-gray-500">Live</span>
                  </div>
                  <p className="text-[11px] text-gray-400 truncate mt-0.5">Workspace broadcast stream</p>
                </div>
              </div>

              {contacts.map((c: any) => (
                <div
                  key={c.id}
                  onClick={() => setActiveContact(c)}
                  className={`
                    p-3 rounded-lg flex items-center gap-3 cursor-pointer spring-transition select-none
                    ${activeContact?.id === c.id ? 'bg-violet-600/20 border border-violet-500/30' : 'hover:bg-white/[0.03]'}
                  `}
                >
                  <div className="w-9 h-9 rounded-xl bg-white/[0.06] border border-white/10 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                    {c.name?.charAt(0) || 'U'}
                  </div>
                  <div className="truncate flex-1">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-semibold text-white truncate">{c.name}</span>
                      <Circle className="w-2 h-2 text-emerald-400 fill-emerald-400" />
                    </div>
                    <p className="text-[11px] text-gray-400 truncate mt-0.5">{c.email}</p>
                  </div>
                </div>
              ))}
            </div>
          </Card>

          {/* Active Conversation Feed */}
          <Card level={1} padded={false} className="md:col-span-2 flex flex-col h-full justify-between overflow-hidden">
            {/* Header */}
            <div className="p-4 border-b border-white/10 flex items-center justify-between bg-white/[0.02]">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-violet-600/30 border border-violet-500/30 flex items-center justify-center text-violet-300 font-bold text-xs">
                  {activeContact?.name?.charAt(0) || '#'}
                </div>
                <div>
                  <h4 className="text-sm font-bold text-white tracking-tight">{activeContact?.name}</h4>
                  <span className="text-[11px] text-gray-400">{activeContact?.email || 'Active channel'}</span>
                </div>
              </div>
              <Badge variant="purple" size="sm">End-to-End Encrypted</Badge>
            </div>

            {/* Message Thread */}
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {messages.map((m) => {
                const isMe = m.sender_id === (auth?.user?.id || 1);
                return (
                  <div key={m.id} className={`flex flex-col ${isMe ? 'items-end' : 'items-start'}`}>
                    <div
                      className={`
                        max-w-[75%] rounded-2xl px-4 py-2.5 text-xs leading-relaxed
                        ${isMe ? 'bg-violet-600 text-white rounded-tr-sm shadow-md' : 'bg-white/[0.06] text-gray-200 border border-white/10 rounded-tl-sm'}
                      `}
                    >
                      <p>{m.text}</p>
                    </div>
                    <span className="text-[10px] text-gray-500 mt-1 px-1">{m.time}</span>
                  </div>
                );
              })}
            </div>

            {/* Message Input Box */}
            <form onSubmit={handleSendMessage} className="p-3 border-t border-white/10 bg-black/40 flex gap-2">
              <input
                type="text"
                placeholder={`Message ${activeContact?.name}...`}
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                className="flex-1 bg-[#12161E] rounded-xl border border-white/15 px-4 py-2 text-xs text-white placeholder:text-gray-500 outline-none focus:border-violet-500"
              />
              <Button type="submit" variant="primary" size="sm" icon={<Send className="w-3.5 h-3.5" />}>
                Send
              </Button>
            </form>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
