import React, { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Head } from '@inertiajs/react';
import { 
    Target, Plus, Play, Pause, XCircle, CheckCircle2, 
    Clock, Sparkles, ShieldCheck, ArrowRight, Activity, Terminal 
} from 'lucide-react';

interface Step {
    id: number;
    sequence: number;
    tool_name: string;
    status: string;
    observation?: string;
    approval_proposal_id?: number;
}

interface Mission {
    id: number;
    name: string;
    objective: string;
    status: string;
    risk_level: string;
    current_step: number;
    progress_summary?: string;
    created_at: string;
    steps?: Step[];
}

interface Props {
    missions: { data: Mission[] };
}

export default function MissionsIndex({ missions: initialMissions }: Props) {
    const [selectedMission, setSelectedMission] = useState<Mission | null>(initialMissions?.data?.[0] || null);
    const [isCreating, setIsCreating] = useState<boolean>(false);
    const [missionName, setMissionName] = useState<string>('');
    const [missionObjective, setMissionObjective] = useState<string>('');
    const [isExecutingStep, setIsExecutingStep] = useState<boolean>(false);

    const handleCreateMission = async () => {
        if (!missionName.trim() || !missionObjective.trim()) return;
        try {
            const res = await fetch('/missions', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' 
                },
                body: JSON.stringify({ name: missionName, objective: missionObjective })
            });
            if (res.ok) {
                const data = await res.json();
                setIsCreating(false);
                setMissionName('');
                setMissionObjective('');
                window.location.reload();
            }
        } catch (err) {
            console.error('Failed to create mission', err);
        }
    };

    const handleExecuteNextStep = async (missionId: number) => {
        setIsExecutingStep(true);
        try {
            const res = await fetch(`/missions/${missionId}/step`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' 
                }
            });
            if (res.ok) {
                const data = await res.json();
                setSelectedMission(data.mission);
            }
        } catch (err) {
            console.error('Failed to execute step', err);
        } finally {
            setIsExecutingStep(false);
        }
    };

    const handleControl = async (missionId: number, action: string) => {
        try {
            const res = await fetch(`/missions/${missionId}/control`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' 
                },
                body: JSON.stringify({ action })
            });
            if (res.ok) {
                const data = await res.json();
                setSelectedMission(data.mission);
            }
        } catch (err) {
            console.error('Failed to control mission', err);
        }
    };

    return (
        <AppShell title="Executive AI Missions">
            <Head title="Missions" />

            <div className="space-y-6">
                {/* Top Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 className="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <Target className="w-6 h-6 text-indigo-500" />
                            <span>Mr. Fox Executive Missions</span>
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Goal-driven autonomous multi-step execution bounded by Brand Profiles, tool allowlists, and human approvals.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setIsCreating(true)}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition-colors"
                        >
                            <Plus className="w-4 h-4" />
                            <span>New Mission</span>
                        </button>
                    </div>
                </div>

                {/* Create Modal */}
                {isCreating && (
                    <div className="p-5 bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-900/50 rounded-xl shadow-lg space-y-4">
                        <h3 className="text-sm font-bold text-slate-800 dark:text-white">Plan New Executive Mission</h3>
                        <div className="space-y-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-500 mb-1">Mission Title</label>
                                <input
                                    type="text"
                                    value={missionName}
                                    onChange={e => setMissionName(e.target.value)}
                                    placeholder="e.g. Inactive Warm Leads Follow-up Campaign"
                                    className="w-full text-sm p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-500 mb-1">Objective & Instructions</label>
                                <textarea
                                    rows={3}
                                    value={missionObjective}
                                    onChange={e => setMissionObjective(e.target.value)}
                                    placeholder="Review warm leads inactive for 5 days, draft personalized follow-ups grounded in Brand Profile, and request approval before sending."
                                    className="w-full text-sm p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg"
                                />
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
                                onClick={handleCreateMission}
                                className="px-4 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold"
                            >
                                Generate Mission Plan
                            </button>
                        </div>
                    </div>
                )}

                {/* 2-Column Missions UI */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Missions List */}
                    <div className="lg:col-span-1 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800 h-[600px] overflow-y-auto">
                        {(!initialMissions?.data || initialMissions.data.length === 0) ? (
                            <div className="p-8 text-center text-sm text-slate-400">No active missions</div>
                        ) : (
                            initialMissions.data.map(m => {
                                const isSelected = selectedMission?.id === m.id;
                                return (
                                    <div
                                        key={m.id}
                                        onClick={() => setSelectedMission(m)}
                                        className={`p-4 cursor-pointer transition-colors ${
                                            isSelected ? 'bg-indigo-50/70 dark:bg-indigo-950/40' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-1">
                                            <span className="font-semibold text-sm text-slate-800 dark:text-white truncate">{m.name}</span>
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                                m.status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                                m.status === 'waiting_for_approval' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' :
                                                'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300'
                                            }`}>
                                                {m.status.toUpperCase()}
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{m.objective}</p>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {/* Right: Selected Mission Execution Timeline */}
                    <div className="lg:col-span-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between">
                        {selectedMission ? (
                            <div className="space-y-6">
                                <div className="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                                    <div>
                                        <h3 className="text-base font-bold text-slate-800 dark:text-white">{selectedMission.name}</h3>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{selectedMission.objective}</p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {selectedMission.status !== 'completed' && selectedMission.status !== 'cancelled' && (
                                            <>
                                                <button
                                                    onClick={() => handleExecuteNextStep(selectedMission.id)}
                                                    disabled={isExecutingStep}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold disabled:opacity-50"
                                                >
                                                    <Play className="w-3.5 h-3.5" />
                                                    <span>{isExecutingStep ? 'Executing...' : 'Run Next Step'}</span>
                                                </button>

                                                <button
                                                    onClick={() => handleControl(selectedMission.id, selectedMission.status === 'paused' ? 'resume' : 'pause')}
                                                    className="p-1.5 text-slate-500 hover:bg-slate-100 rounded-lg"
                                                >
                                                    {selectedMission.status === 'paused' ? <Play className="w-4 h-4" /> : <Pause className="w-4 h-4" />}
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </div>

                                {/* Step Execution Timeline */}
                                <div className="space-y-3">
                                    <div className="text-xs font-bold uppercase tracking-wider text-slate-400">Execution Plan & Steps</div>

                                    {(!selectedMission.steps || selectedMission.steps.length === 0) ? (
                                        <div className="text-xs text-slate-400 p-4 border border-dashed rounded-lg text-center">
                                            No steps planned yet.
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {selectedMission.steps.map((s, idx) => (
                                                <div key={s.id || idx} className="p-3 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 flex items-start gap-3">
                                                    <div className="mt-0.5">
                                                        {s.status === 'completed' ? <CheckCircle2 className="w-4 h-4 text-emerald-500" /> :
                                                         s.status === 'waiting_for_approval' ? <ShieldCheck className="w-4 h-4 text-amber-500" /> :
                                                         <Clock className="w-4 h-4 text-slate-400" />}
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">Step {s.sequence}: {s.tool_name}</span>
                                                            <span className="text-[10px] font-semibold text-slate-400 uppercase">{s.status}</span>
                                                        </div>
                                                        {s.observation && (
                                                            <p className="text-xs text-slate-600 dark:text-slate-300 mt-1">{s.observation}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="h-full flex flex-col items-center justify-center text-slate-400 p-8 text-center">
                                <Target className="w-12 h-12 mb-3 stroke-[1.2]" />
                                <h4 className="text-base font-semibold text-slate-600 dark:text-slate-300">Select a mission</h4>
                                <p className="text-xs text-slate-400 mt-1 max-w-sm">Choose an executive mission from the list on the left to monitor progress and execute steps.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppShell>
    );
}
