import React, { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Head } from '@inertiajs/react';
import { 
    Zap, Plus, Play, Pause, AlertCircle, CheckCircle2, 
    Clock, ArrowRight, Activity, Filter, Settings2, ShieldCheck, ChevronRight 
} from 'lucide-react';

interface Trigger {
    name: string;
    description: string;
    module: string;
}

interface Action {
    name: string;
    description: string;
    risk_level?: string;
}

interface Rule {
    id: number;
    name: string;
    description?: string;
    enabled: boolean;
    trigger_type: string;
    action_config: Array<{ action: string; input?: Record<string, any> }>;
    run_count: number;
    failure_count: number;
    last_run_at?: string;
}

interface Run {
    id: number;
    trigger_event: string;
    status: string;
    started_at: string;
    rule?: { name: string };
}

interface Props {
    rules: { data: Rule[] };
    recentRuns: Run[];
    triggers: Trigger[];
    actions: Action[];
}

export default function AutomationIndex({ rules, recentRuns, triggers, actions }: Props) {
    const [activeTab, setActiveTab] = useState<'rules' | 'runs'>('rules');
    const [isCreating, setIsCreating] = useState<boolean>(false);
    const [newRuleName, setNewRuleName] = useState<string>('');
    const [selectedTrigger, setSelectedTrigger] = useState<string>(triggers[0]?.name || 'crm.lead.created');
    const [selectedAction, setSelectedAction] = useState<string>(actions[0]?.name || 'tasks.create_task');
    const [taskTitle, setTaskTitle] = useState<string>('Follow up with qualified lead');

    const handleCreateRule = async () => {
        if (!newRuleName.trim()) return;
        try {
            const res = await fetch('/automations', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' 
                },
                body: JSON.stringify({
                    name: newRuleName,
                    trigger_type: selectedTrigger,
                    action_config: [{ action: selectedAction, input: { title: taskTitle } }],
                    enabled: true,
                })
            });
            if (res.ok) {
                setIsCreating(false);
                setNewRuleName('');
                window.location.reload();
            }
        } catch (err) {
            console.error('Failed to create rule', err);
        }
    };

    const handleToggleRule = async (ruleId: number) => {
        try {
            await fetch(`/automations/${ruleId}/toggle`, {
                method: 'PATCH',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' 
                }
            });
            window.location.reload();
        } catch (err) {
            console.error('Failed to toggle rule', err);
        }
    };

    return (
        <AppShell title="Business Automations">
            <Head title="Automations" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 className="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <Zap className="w-6 h-6 text-amber-500" />
                            <span>Business Automations Engine</span>
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Deterministic Event-Condition-Action workflows for ERP & Communications.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setIsCreating(true)}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition-colors"
                        >
                            <Plus className="w-4 h-4" />
                            <span>New Automation</span>
                        </button>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex border-b border-slate-200 dark:border-slate-800">
                    <button
                        onClick={() => setActiveTab('rules')}
                        className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                            activeTab === 'rules'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
                        }`}
                    >
                        Active Rules ({rules?.data?.length || 0})
                    </button>
                    <button
                        onClick={() => setActiveTab('runs')}
                        className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                            activeTab === 'runs'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
                        }`}
                    >
                        Execution History ({recentRuns?.length || 0})
                    </button>
                </div>

                {/* Modal for Creating Rule */}
                {isCreating && (
                    <div className="p-5 bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-900/50 rounded-xl shadow-lg space-y-4">
                        <h3 className="text-sm font-bold text-slate-800 dark:text-white">Create Deterministic Automation</h3>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-500 mb-1">Rule Name</label>
                                <input
                                    type="text"
                                    value={newRuleName}
                                    onChange={e => setNewRuleName(e.target.value)}
                                    placeholder="e.g. Qualified Lead Follow-up"
                                    className="w-full text-sm p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-500 mb-1">WHEN (Trigger Event)</label>
                                <select
                                    value={selectedTrigger}
                                    onChange={e => setSelectedTrigger(e.target.value)}
                                    className="w-full text-sm p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg"
                                >
                                    {triggers.map(t => (
                                        <option key={t.name} value={t.name}>{t.name} — {t.description}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-500 mb-1">THEN (Execute Action)</label>
                                <select
                                    value={selectedAction}
                                    onChange={e => setSelectedAction(e.target.value)}
                                    className="w-full text-sm p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg"
                                >
                                    {actions.map(a => (
                                        <option key={a.name} value={a.name}>{a.name} — {a.description}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-2">
                            <button
                                onClick={() => setIsCreating(false)}
                                className="px-3 py-1.5 text-xs text-slate-500 hover:bg-slate-100 rounded-lg"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleCreateRule}
                                className="px-4 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold"
                            >
                                Save Rule
                            </button>
                        </div>
                    </div>
                )}

                {/* Tab: Rules List */}
                {activeTab === 'rules' && (
                    <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800">
                        {(!rules?.data || rules.data.length === 0) ? (
                            <div className="p-8 text-center text-sm text-slate-400">
                                No automation rules configured. Click "New Automation" to create your first rule.
                            </div>
                        ) : (
                            rules.data.map(rule => (
                                <div key={rule.id} className="p-4 flex items-center justify-between hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-sm text-slate-800 dark:text-white">{rule.name}</span>
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                                rule.enabled 
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                            }`}>
                                                {rule.enabled ? 'ACTIVE' : 'PAUSED'}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-mono">
                                            <span>WHEN: <strong className="text-indigo-600 dark:text-indigo-400">{rule.trigger_type}</strong></span>
                                            <ArrowRight className="w-3 h-3 text-slate-400" />
                                            <span>THEN: <strong className="text-amber-600 dark:text-amber-400">{rule.action_config[0]?.action}</strong></span>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <div className="text-right text-xs text-slate-400">
                                            <div>Runs: <strong className="text-slate-700 dark:text-slate-200">{rule.run_count}</strong></div>
                                            {rule.failure_count > 0 && <div className="text-red-500">Fails: {rule.failure_count}</div>}
                                        </div>

                                        <button
                                            onClick={() => handleToggleRule(rule.id)}
                                            className={`p-2 rounded-lg border text-xs font-semibold ${
                                                rule.enabled 
                                                    ? 'border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300'
                                                    : 'border-emerald-500 text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40'
                                            }`}
                                        >
                                            {rule.enabled ? <Pause className="w-3.5 h-3.5" /> : <Play className="w-3.5 h-3.5" />}
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* Tab: Runs History */}
                {activeTab === 'runs' && (
                    <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800">
                        {(!recentRuns || recentRuns.length === 0) ? (
                            <div className="p-8 text-center text-sm text-slate-400">No execution logs recorded yet.</div>
                        ) : (
                            recentRuns.map(run => (
                                <div key={run.id} className="p-4 flex items-center justify-between">
                                    <div>
                                        <div className="font-medium text-sm text-slate-800 dark:text-white">
                                            {run.rule?.name || run.trigger_event}
                                        </div>
                                        <div className="text-xs text-slate-400 font-mono mt-0.5">
                                            Trigger: {run.trigger_event} • Started: {new Date(run.started_at).toLocaleTimeString()}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${
                                            run.status === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                            run.status === 'waiting_for_approval' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                                            'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                        }`}>
                                            {run.status.toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}
            </div>
        </AppShell>
    );
}
