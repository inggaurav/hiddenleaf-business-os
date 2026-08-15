import React, { useState, useEffect } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';
import { 
    Activity, AlertTriangle, ArrowRight, CheckCircle2, 
    Clock, DollarSign, Filter, Layers, MessageSquare, Play, 
    Plus, Search, ShieldAlert, ShieldCheck, Sparkles, Target, 
    TrendingDown, TrendingUp, Users, Zap, X, ChevronRight, ExternalLink
} from 'lucide-react';

interface HealthDim {
    key: string;
    name: string;
    score: number;
    status: 'healthy' | 'watch' | 'warning' | 'critical' | 'unknown';
    trend: string;
    signals: string[];
    evidence: Array<{ type: string; id: number; label: string; route?: string }>;
}

interface Priority {
    rank: number;
    id: string;
    title: string;
    why_it_matters: string;
    category: string;
    severity: 'info' | 'attention' | 'warning' | 'critical';
    financial_impact?: number;
    owner?: string;
    due_or_age?: string;
    recommended_next_action?: string;
    evidence: Array<{ type: string; id: number; label: string; route?: string }>;
    actions: Array<{ label: string; type: string; route?: string; action_name?: string }>;
}

interface Recommendation {
    id: string;
    title: string;
    rationale: string;
    category: string;
    severity: string;
    fingerprint: string;
    evidence: Array<{ type: string; id: number; label: string; route?: string }>;
    actions: Array<{ label: string; type: string; route?: string; action_name?: string }>;
}

interface Briefing {
    period: string;
    summary_headline: string;
    top_changes: Array<{ metric: string; current: any; previous: any; delta_percentage: number; direction: string }>;
    top_priorities: Priority[];
    financial_snapshot: Record<string, any>;
    sales_snapshot: Record<string, any>;
    communications_snapshot: Record<string, any>;
    operations_snapshot: Record<string, any>;
    waiting_approvals: { pending_count: number; route: string };
    recommended_actions: Recommendation[];
}

interface TimelineItem {
    id: string;
    domain: string;
    event_type: string;
    title: string;
    description: string;
    timestamp: string;
    actor_name?: string;
    route?: string;
    severity: string;
}

interface Props {
    health: { overall: HealthDim; dimensions: Record<string, HealthDim> } | null;
    priorities: Priority[];
    recommendations: Recommendation[];
    briefing: Briefing | null;
    anomalies: any[];
    timeline: TimelineItem[];
    runningSystems: { active_automations: number; running_missions: number; waiting_approvals: number };
}

export default function CommandCenterIndex({ 
    health, 
    priorities, 
    recommendations, 
    briefing, 
    anomalies, 
    timeline, 
    runningSystems 
}: Props) {
    const [searchOpen, setSearchOpen] = useState<boolean>(false);
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [isSearching, setIsSearching] = useState<boolean>(false);

    // Command Palette shortcut (Cmd/Ctrl + K)
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setSearchOpen(prev => !prev);
            }
            if (e.key === 'Escape') {
                setSearchOpen(false);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    // Live search query
    useEffect(() => {
        if (!searchQuery.trim() || searchQuery.length < 2) {
            setSearchResults([]);
            return;
        }

        const timeout = setTimeout(async () => {
            setIsSearching(true);
            try {
                const res = await fetch(`/command-center/search?q=${encodeURIComponent(searchQuery)}`);
                if (res.ok) {
                    const data = await res.json();
                    setSearchResults(data);
                }
            } catch (err) {
                console.error('Search error', err);
            } finally {
                setIsSearching(false);
            }
        }, 200);

        return () => clearTimeout(timeout);
    }, [searchQuery]);

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'healthy': return 'text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800';
            case 'watch': return 'text-sky-600 bg-sky-50 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800';
            case 'warning': return 'text-amber-600 bg-amber-50 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800';
            case 'critical': return 'text-rose-600 bg-rose-50 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800';
            default: return 'text-slate-600 bg-slate-50 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
        }
    };

    return (
        <AppShell title="Executive Command Center">
            <Head title="Command Center" />

            {/* Global Search Modal (Cmd+K) */}
            {searchOpen && (
                <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 bg-slate-900/60 backdrop-blur-sm p-4">
                    <div className="bg-white dark:bg-slate-900 w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-3">
                            <Search className="w-5 h-5 text-slate-400" />
                            <input
                                autoFocus
                                type="text"
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                placeholder="Search leads, invoices, messages, tasks, knowledge, missions... (Esc to close)"
                                className="flex-1 text-sm bg-transparent border-none outline-none text-slate-800 dark:text-white placeholder:text-slate-400"
                            />
                            <button onClick={() => setSearchOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 p-2">
                            {isSearching ? (
                                <div className="p-6 text-center text-xs text-slate-400">Searching cross-domain business records...</div>
                            ) : searchResults.length === 0 ? (
                                <div className="p-6 text-center text-xs text-slate-400">
                                    {searchQuery.length >= 2 ? 'No matching records found.' : 'Type at least 2 characters to search across all modules.'}
                                </div>
                            ) : (
                                searchResults.map((r, i) => (
                                    <Link
                                        key={i}
                                        href={r.route}
                                        onClick={() => setSearchOpen(false)}
                                        className="p-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/60 flex items-center justify-between transition-colors block"
                                    >
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-semibold text-slate-800 dark:text-white">{r.title}</span>
                                                {r.badge && (
                                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                                        {r.badge}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{r.subtitle}</p>
                                        </div>
                                        <ChevronRight className="w-4 h-4 text-slate-400" />
                                    </Link>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            )}

            <div className="space-y-6">
                {/* Header with Search & Executive Actions */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <Sparkles className="w-6 h-6 text-indigo-500" />
                            <h1 className="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                                Executive Command Center
                            </h1>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Operational intelligence, health dimensions, and high-impact business priorities.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setSearchOpen(true)}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-medium transition-colors border border-slate-200/60 dark:border-slate-700/60"
                        >
                            <Search className="w-3.5 h-3.5 text-slate-400" />
                            <span>Quick Search</span>
                            <kbd className="px-1.5 py-0.5 text-[10px] bg-white dark:bg-slate-900 border rounded text-slate-400 shadow-sm">⌘K</kbd>
                        </button>

                        <Link
                            href="/command-center/approvals"
                            className="relative inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-colors"
                        >
                            <ShieldCheck className="w-4 h-4" />
                            <span>Approvals</span>
                            {runningSystems.waiting_approvals > 0 && (
                                <span className="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-rose-500 text-white rounded-full text-[10px] font-bold ring-2 ring-white dark:ring-slate-900">
                                    {runningSystems.waiting_approvals}
                                </span>
                            )}
                        </Link>
                    </div>
                </div>

                {/* Top Section: Business Health & Briefing Banner */}
                <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    {/* Overall Score Card */}
                    <div className="lg:col-span-1 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Business Health</span>
                            <div className="mt-3 flex items-baseline gap-3">
                                <span className="text-5xl font-black tracking-tight text-slate-900 dark:text-white">
                                    {health?.overall.score ?? 100}
                                </span>
                                <span className="text-xs font-semibold text-slate-400">/100</span>
                                <span className={`ml-auto px-2.5 py-1 rounded-full text-xs font-bold uppercase border ${getStatusColor(health?.overall.status ?? 'healthy')}`}>
                                    {health?.overall.status ?? 'HEALTHY'}
                                </span>
                            </div>
                        </div>

                        <div className="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                            <div className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
                                {health?.overall.signals[0] ?? 'All business parameters functioning normally.'}
                            </div>
                        </div>
                    </div>

                    {/* Briefing & Active Systems Snapshot */}
                    <div className="lg:col-span-3 p-6 rounded-2xl bg-gradient-to-br from-indigo-900 to-slate-900 text-white shadow-md flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold uppercase tracking-wider text-indigo-300 flex items-center gap-1.5">
                                    <Sparkles className="w-3.5 h-3.5" />
                                    <span>Executive Morning Briefing</span>
                                </span>
                                <span className="text-[11px] text-slate-400 font-mono">Today's Snapshot</span>
                            </div>
                            <h3 className="text-lg font-bold mt-2 text-white leading-snug">
                                {briefing?.summary_headline ?? 'Workspace systems synchronized and operating normally.'}
                            </h3>
                        </div>

                        <div className="grid grid-cols-3 gap-4 mt-6 pt-4 border-t border-indigo-800/50 text-center">
                            <div>
                                <span className="text-xs text-indigo-200">Active Automations</span>
                                <div className="text-xl font-bold mt-0.5 text-white">{runningSystems.active_automations}</div>
                            </div>
                            <div>
                                <span className="text-xs text-indigo-200">Running Missions</span>
                                <div className="text-xl font-bold mt-0.5 text-white">{runningSystems.running_missions}</div>
                            </div>
                            <div>
                                <span className="text-xs text-indigo-200">Pending Approvals</span>
                                <div className="text-xl font-bold mt-0.5 text-amber-300">{runningSystems.waiting_approvals}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Dimension Cards */}
                {health?.dimensions && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        {Object.values(health.dimensions).map((dim) => (
                            <div key={dim.key} className="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                <div className="text-[11px] font-semibold text-slate-400 truncate">{dim.name}</div>
                                <div className="flex items-baseline justify-between mt-1.5">
                                    <span className="text-lg font-bold text-slate-800 dark:text-white">{dim.score}</span>
                                    <span className={`px-1.5 py-0.5 rounded text-[9px] font-bold uppercase ${getStatusColor(dim.status)}`}>
                                        {dim.status}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Main 2-Column Section: Top Priorities & Recommendations */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Columns: Ranked Priorities */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Target className="w-5 h-5 text-indigo-500" />
                                <span>Ranked Executive Priorities</span>
                            </h2>
                            <span className="text-xs text-slate-400">Sorted by Severity & Financial Impact</span>
                        </div>

                        {priorities.length === 0 ? (
                            <div className="p-8 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-400 text-sm">
                                <CheckCircle2 className="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                                <span>No outstanding critical priorities. All dimensions are healthy.</span>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {priorities.map((p) => (
                                    <div 
                                        key={p.id}
                                        className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-start gap-3">
                                                <span className="w-6 h-6 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                                                    {p.rank}
                                                </span>
                                                <div>
                                                    <h4 className="text-sm font-bold text-slate-800 dark:text-white">{p.title}</h4>
                                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{p.why_it_matters}</p>
                                                </div>
                                            </div>

                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${getStatusColor(p.severity)}`}>
                                                {p.severity}
                                            </span>
                                        </div>

                                        {/* Evidence & Next Action */}
                                        {p.recommended_next_action && (
                                            <div className="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl text-xs text-slate-600 dark:text-slate-300 flex items-center justify-between gap-3">
                                                <span><strong>Recommended Next:</strong> {p.recommended_next_action}</span>
                                                <div className="flex items-center gap-2 shrink-0">
                                                    {p.actions.map((act, aIdx) => (
                                                        act.route ? (
                                                            <Link
                                                                key={aIdx}
                                                                href={act.route}
                                                                className="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[11px] font-semibold transition-colors"
                                                            >
                                                                <span>{act.label}</span>
                                                                <ExternalLink className="w-3 h-3" />
                                                            </Link>
                                                        ) : null
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Right Column: Mr. Fox Recommendations & Recent Activity */}
                    <div className="space-y-6">
                        {/* Recommendations */}
                        <div className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Sparkles className="w-4 h-4 text-indigo-500" />
                                <span>Evidence-Backed Recommendations</span>
                            </h3>

                            {recommendations.length === 0 ? (
                                <p className="text-xs text-slate-400">No active recommendations at this time.</p>
                            ) : (
                                <div className="space-y-3">
                                    {recommendations.map((rec) => (
                                        <div key={rec.id} className="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-2">
                                            <h5 className="text-xs font-bold text-slate-800 dark:text-white">{rec.title}</h5>
                                            <p className="text-[11px] text-slate-500 dark:text-slate-400">{rec.rationale}</p>
                                            <div className="flex items-center gap-2 pt-1">
                                                {rec.actions.map((act, i) => (
                                                    act.route ? (
                                                        <Link
                                                            key={i}
                                                            href={act.route}
                                                            className="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold hover:underline flex items-center gap-1"
                                                        >
                                                            <span>{act.label}</span>
                                                            <ArrowRight className="w-3 h-3" />
                                                        </Link>
                                                    ) : null
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Recent Activity Timeline */}
                        <div className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Activity className="w-4 h-4 text-slate-500" />
                                <span>Executive Activity Stream</span>
                            </h3>

                            <div className="space-y-3 max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                                {timeline.length === 0 ? (
                                    <p className="text-xs text-slate-400">No recent activity recorded.</p>
                                ) : (
                                    timeline.map((item) => (
                                        <div key={item.id} className="pt-2.5 first:pt-0">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{item.title}</span>
                                                <span className="text-[10px] text-slate-400 font-mono">
                                                    {new Date(item.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </span>
                                            </div>
                                            <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">{item.description}</p>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppShell>
    );
}
