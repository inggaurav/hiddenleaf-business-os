import React, { useState, useEffect } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';
import { 
    Mail, MessageSquare, Hash, Instagram, Facebook, 
    Send, Sparkles, AlertCircle, CheckCircle2, 
    Clock, RefreshCw, User, Link as LinkIcon, Search, ShieldCheck 
} from 'lucide-react';

interface Account {
    id: number;
    provider: string;
    display_name: string;
    status: string;
}

interface Message {
    id: number;
    direction: 'inbound' | 'outbound';
    sender_name: string;
    body_text: string;
    delivery_status: string;
    sent_at: string;
}

interface Conversation {
    id: number;
    provider: string;
    subject: string;
    participant_name: string;
    participant_identifier: string;
    last_message_preview: string;
    last_message_at: string;
    unread_count: number;
    priority_score: number;
    sentiment: string;
    intent: string;
    status: string;
    linked_entity_type?: string;
    linked_entity_id?: number;
}

interface Props {
    conversations: {
        data: Conversation[];
        total: number;
    };
    accounts: Account[];
}

export default function Inbox({ conversations: initialConversations, accounts }: Props) {
    const [selectedChannel, setSelectedChannel] = useState<string>('all');
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [activeConversation, setActiveConversation] = useState<Conversation | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [replyText, setReplyText] = useState<string>('');
    const [isDrafting, setIsDrafting] = useState<boolean>(false);
    const [isSending, setIsSending] = useState<boolean>(false);
    const [reviewScore, setReviewScore] = useState<number | null>(null);
    const [reviewStatus, setReviewStatus] = useState<string | null>(null);

    const channels = [
        { id: 'all', name: 'All Channels', icon: MessageSquare },
        { id: 'gmail', name: 'Gmail / Email', icon: Mail },
        { id: 'whatsapp', name: 'WhatsApp', icon: MessageSquare },
        { id: 'slack', name: 'Slack', icon: Hash },
        { id: 'instagram', name: 'Instagram', icon: Instagram },
        { id: 'facebook', name: 'Facebook', icon: Facebook },
        { id: 'internal', name: 'Internal Chat', icon: User },
    ];

    const filteredConversations = (initialConversations?.data || []).filter(c => {
        const matchesChannel = selectedChannel === 'all' || c.provider === selectedChannel;
        const matchesQuery = !searchQuery || 
            c.participant_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            c.subject?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            c.last_message_preview?.toLowerCase().includes(searchQuery.toLowerCase());
        return matchesChannel && matchesQuery;
    });

    const loadThread = async (conversation: Conversation) => {
        setActiveConversation(conversation);
        setReviewScore(null);
        setReviewStatus(null);
        setReplyText('');
        try {
            const res = await fetch(`/api/v1/communications/conversations/${conversation.id}`);
            if (res.ok) {
                const data = await res.json();
                setMessages(data.messages || []);
            }
        } catch (err) {
            console.error('Failed to load thread messages', err);
        }
    };

    const handleGenerateDraft = async () => {
        if (!activeConversation) return;
        setIsDrafting(true);
        try {
            const res = await fetch(`/api/v1/communications/conversations/${activeConversation.id}/draft`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' }
            });
            if (res.ok) {
                const data = await res.json();
                setReplyText(data.draft_body || '');
                setReviewScore(data.review_score);
                setReviewStatus(data.review_status);
            }
        } catch (err) {
            console.error('Failed to draft reply', err);
        } finally {
            setIsDrafting(false);
        }
    };

    const handleSendReply = async () => {
        if (!activeConversation || !replyText.trim()) return;
        setIsSending(true);
        try {
            const res = await fetch(`/api/v1/communications/conversations/${activeConversation.id}/reply`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
                body: JSON.stringify({ message_body: replyText, idempotency_key: `msg_${Date.now()}` })
            });
            if (res.ok) {
                setReplyText('');
                loadThread(activeConversation);
            }
        } catch (err) {
            console.error('Failed to send reply', err);
        } finally {
            setIsSending(false);
        }
    };

    const getChannelIcon = (provider: string) => {
        switch (provider) {
            case 'gmail': return <Mail className="w-4 h-4 text-red-500" />;
            case 'whatsapp': return <MessageSquare className="w-4 h-4 text-emerald-500" />;
            case 'slack': return <Hash className="w-4 h-4 text-purple-500" />;
            case 'instagram': return <Instagram className="w-4 h-4 text-pink-500" />;
            case 'facebook': return <Facebook className="w-4 h-4 text-blue-600" />;
            default: return <User className="w-4 h-4 text-slate-500" />;
        }
    };

    return (
        <AppShell title="Unified Communications Inbox">
            <Head title="Unified Inbox" />

            <div className="h-[calc(100vh-140px)] flex flex-col md:flex-row bg-white dark:bg-slate-900 rounded-xl shadow border border-slate-200 dark:border-slate-800 overflow-hidden">
                {/* 1. Channel Filter Column */}
                <div className="w-full md:w-56 bg-slate-50 dark:bg-slate-950 border-r border-slate-200 dark:border-slate-800 p-3 flex flex-col gap-1">
                    <div className="text-xs font-bold uppercase tracking-wider text-slate-400 px-3 py-2">Channels</div>
                    {channels.map(ch => {
                        const Icon = ch.icon;
                        const isSelected = selectedChannel === ch.id;
                        return (
                            <button
                                key={ch.id}
                                onClick={() => setSelectedChannel(ch.id)}
                                className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    isSelected 
                                        ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 font-semibold' 
                                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-900'
                                }`}
                            >
                                <Icon className="w-4 h-4" />
                                <span>{ch.name}</span>
                            </button>
                        );
                    })}
                </div>

                {/* 2. Conversations List Column */}
                <div className="w-full md:w-80 border-r border-slate-200 dark:border-slate-800 flex flex-col bg-white dark:bg-slate-900">
                    <div className="p-3 border-b border-slate-200 dark:border-slate-800">
                        <div className="relative">
                            <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search contacts, messages..."
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                className="w-full pl-9 pr-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                        {filteredConversations.length === 0 ? (
                            <div className="p-8 text-center text-sm text-slate-400">No conversations in this channel</div>
                        ) : (
                            filteredConversations.map(conv => {
                                const isSelected = activeConversation?.id === conv.id;
                                return (
                                    <div
                                        key={conv.id}
                                        onClick={() => loadThread(conv)}
                                        className={`p-3.5 cursor-pointer transition-colors ${
                                            isSelected 
                                                ? 'bg-indigo-50/70 dark:bg-indigo-950/30' 
                                                : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-1">
                                            <div className="flex items-center gap-1.5 font-medium text-sm text-slate-800 dark:text-white truncate">
                                                {getChannelIcon(conv.provider)}
                                                <span className="truncate">{conv.participant_name}</span>
                                            </div>
                                            {conv.priority_score >= 75 && (
                                                <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                    Urgent
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 truncate">{conv.last_message_preview}</p>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>

                {/* 3. Active Conversation Thread & Reply View */}
                <div className="flex-1 flex flex-col bg-slate-50/50 dark:bg-slate-900/50">
                    {activeConversation ? (
                        <>
                            {/* Thread Header */}
                            <div className="p-4 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-full bg-slate-100 dark:bg-slate-800">
                                        {getChannelIcon(activeConversation.provider)}
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-semibold text-slate-800 dark:text-white">{activeConversation.participant_name}</h3>
                                        <p className="text-xs text-slate-400">{activeConversation.participant_identifier}</p>
                                    </div>
                                </div>
                                {activeConversation.linked_entity_type && (
                                    <div className="flex items-center gap-1 text-xs px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        <LinkIcon className="w-3 h-3 text-indigo-500" />
                                        <span>Linked to {activeConversation.linked_entity_type}</span>
                                    </div>
                                )}
                            </div>

                            {/* Messages Stream */}
                            <div className="flex-1 p-4 overflow-y-auto space-y-3">
                                {messages.map(msg => {
                                    const isInbound = msg.direction === 'inbound';
                                    return (
                                        <div key={msg.id} className={`flex flex-col ${isInbound ? 'items-start' : 'items-end'}`}>
                                            <div className={`max-w-[75%] rounded-2xl px-4 py-2.5 text-sm shadow-sm ${
                                                isInbound 
                                                    ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-100 dark:border-slate-700' 
                                                    : 'bg-indigo-600 text-white'
                                            }`}>
                                                <p className="whitespace-pre-wrap">{msg.body_text}</p>
                                            </div>
                                            <span className="text-[10px] text-slate-400 mt-1 px-1">{msg.sent_at ? new Date(msg.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}</span>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Reply Composer with Mr. Fox Intelligence */}
                            <div className="p-4 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 space-y-3">
                                <div className="flex items-center justify-between">
                                    <button
                                        onClick={handleGenerateDraft}
                                        disabled={isDrafting}
                                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 bg-indigo-50 dark:bg-indigo-950/60 px-2.5 py-1 rounded-md transition-colors"
                                    >
                                        <Sparkles className="w-3.5 h-3.5" />
                                        <span>{isDrafting ? 'Drafting Brand Reply...' : 'Mr. Fox Draft Reply'}</span>
                                    </button>

                                    {reviewScore !== null && (
                                        <div className="flex items-center gap-1 text-xs px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            <ShieldCheck className="w-3.5 h-3.5" />
                                            <span>Review Score: {reviewScore}/100</span>
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-2">
                                    <textarea
                                        rows={3}
                                        value={replyText}
                                        onChange={e => setReplyText(e.target.value)}
                                        placeholder={`Reply to ${activeConversation.participant_name} via ${activeConversation.provider}...`}
                                        className="flex-1 p-2.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    />
                                    <button
                                        onClick={handleSendReply}
                                        disabled={isSending || !replyText.trim()}
                                        className="self-end p-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-50 transition-colors"
                                    >
                                        <Send className="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </>
                    ) : (
                        <div className="flex-1 flex flex-col items-center justify-center text-slate-400 p-8 text-center">
                            <MessageSquare className="w-12 h-12 mb-3 stroke-[1.2]" />
                            <h4 className="text-base font-semibold text-slate-600 dark:text-slate-300">Select a conversation</h4>
                            <p className="text-xs text-slate-400 mt-1 max-w-sm">Choose any customer, client, or team thread from the list on the left to read and send messages.</p>
                        </div>
                    )}
                </div>
            </div>
        </AppShell>
    );
}
