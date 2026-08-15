import React, { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';
import { 
    ShieldCheck, ShieldAlert, CheckCircle2, XCircle, 
    Clock, ArrowRight, Activity, Terminal, AlertCircle 
} from 'lucide-react';

interface Proposal {
    id: number;
    tool_name: string;
    payload: Record<string, any>;
    human_summary: string;
    risk_level: string;
    status: string;
    requested_at: string;
    expires_at?: string;
    user?: { name: string; email: string };
    approver?: { name: string };
}

interface Props {
    proposals: { data: Proposal[]; links: any[] };
}

export default function ApprovalsIndex({ proposals: initialProposals }: Props) {
    const [proposals, setProposals] = useState<Proposal[]>(initialProposals?.data || []);
    const [processingId, setProcessingId] = useState<number | null>(null);

    const handleAction = async (id: number, action: 'approve' | 'reject') => {
        setProcessingId(id);
        try {
            const res = await fetch(`/command-center/approvals/${id}/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                setProposals(prev => prev.map(p => p.id === id ? { ...p, status: action === 'approve' ? 'approved' : 'rejected' } : p));
            }
        } catch (err) {
            console.error('Approval action error', err);
        } finally {
            setProcessingId(null);
        }
    };

    return (
        <AppShell title="Unified Approvals Center">
            <Head title="Approvals Center" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <ShieldCheck className="w-6 h-6 text-indigo-500" />
                            <span>Unified Approvals Center</span>
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Governance and human-in-the-loop authorization for high-risk AI actions and outbound communications.
                        </p>
                    </div>

                    <Link
                        href="/command-center"
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-200"
                    >
                        <span>← Back to Command Center</span>
                    </Link>
                </div>

                {/* Queue Table */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800 overflow-hidden shadow-sm">
                    {proposals.length === 0 ? (
                        <div className="p-12 text-center text-slate-400 text-sm">
                            <CheckCircle2 className="w-10 h-10 text-emerald-500 mx-auto mb-3" />
                            <h4 className="font-semibold text-slate-700 dark:text-slate-200">No Action Proposals Pending</h4>
                            <p className="text-xs text-slate-400 mt-1">All mission and automation actions are fully authorized.</p>
                        </div>
                    ) : (
                        proposals.map((prop) => (
                            <div key={prop.id} className="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <div className="space-y-1.5 max-w-2xl">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-bold text-slate-800 dark:text-white">{prop.human_summary}</span>
                                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                            prop.status === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' :
                                            prop.status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                                        }`}>
                                            {prop.status}
                                        </span>
                                    </div>

                                    <div className="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 font-mono">
                                        <span>Tool: <strong className="text-indigo-600 dark:text-indigo-400">{prop.tool_name}</strong></span>
                                        <span>•</span>
                                        <span>Risk: <strong className="text-rose-600 dark:text-rose-400">{prop.risk_level}</strong></span>
                                        <span>•</span>
                                        <span>Requested: {new Date(prop.requested_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                    </div>

                                    {/* Parameter Payload Preview */}
                                    <div className="p-2.5 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-[11px] font-mono text-slate-600 dark:text-slate-300 mt-2 overflow-x-auto">
                                        <pre>{JSON.stringify(prop.payload, null, 2)}</pre>
                                    </div>
                                </div>

                                {prop.status === 'pending' && (
                                    <div className="flex items-center gap-2 shrink-0">
                                        <button
                                            onClick={() => handleAction(prop.id, 'reject')}
                                            disabled={processingId === prop.id}
                                            className="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition-colors disabled:opacity-50"
                                        >
                                            Reject
                                        </button>
                                        <button
                                            onClick={() => handleAction(prop.id, 'approve')}
                                            disabled={processingId === prop.id}
                                            className="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors disabled:opacity-50 flex items-center gap-1.5"
                                        >
                                            <CheckCircle2 className="w-3.5 h-3.5" />
                                            <span>{processingId === prop.id ? 'Executing...' : 'Approve & Run'}</span>
                                        </button>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </div>
        </AppShell>
    );
}
