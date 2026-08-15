import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { 
    Building, Sparkles, Users, Radio, MessageSquare, 
    CheckCircle2, ArrowRight, ArrowLeft, RefreshCw, Zap, ShieldCheck 
} from 'lucide-react';

interface Props {
    workspace: any;
    brand: any;
    isCompleted: boolean;
}

export default function OnboardingWizard({ workspace, brand, isCompleted }: Props) {
    const [step, setStep] = useState<number>(1);
    const [loading, setLoading] = useState<boolean>(false);
    const [demoLoaded, setDemoLoaded] = useState<boolean>(false);

    // Form states
    const [formData, setFormData] = useState({
        company_name: workspace?.organization?.name || '',
        currency: 'USD',
        timezone: 'UTC',
        brand_name: brand?.brand_name || workspace?.organization?.name || '',
        company_description: brand?.company_description || '',
        target_audience: brand?.target_audience || '',
        tone_of_voice: brand?.tone_of_voice || 'Professional, decisive, and helpful',
        teammate_emails: '',
        default_model: 'gemini-1.5-pro',
    });

    const handleSaveStep = async (nextStep: number) => {
        setLoading(true);
        try {
            await fetch('/onboarding/step', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ step, data: formData }),
            });
            setStep(nextStep);
        } catch (err) {
            console.error('Failed to save step', err);
        } finally {
            setLoading(false);
        }
    };

    const handleComplete = async () => {
        setLoading(true);
        try {
            const res = await fetch('/onboarding/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            const data = await res.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        } catch (err) {
            console.error('Failed to complete onboarding', err);
        } finally {
            setLoading(false);
        }
    };

    const handleLoadDemo = async () => {
        setLoading(true);
        try {
            const res = await fetch('/onboarding/demo-data/load', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            if (res.ok) {
                setDemoLoaded(true);
            }
        } catch (err) {
            console.error('Failed to load demo data', err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between p-6 sm:p-10 font-sans selection:bg-indigo-500 selection:text-white">
            <Head title="Welcome to HiddenLeaf Business OS" />

            {/* Top Bar */}
            <div className="max-w-4xl mx-auto w-full flex items-center justify-between pb-8">
                <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/30">
                        HL
                    </div>
                    <span className="font-bold tracking-tight text-white text-lg">HiddenLeaf Business OS</span>
                </div>

                <div className="text-xs text-slate-400 font-mono">
                    Step {step} of 6
                </div>
            </div>

            {/* Center Card */}
            <div className="max-w-2xl mx-auto w-full bg-slate-900/90 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl backdrop-blur-xl">
                {/* Step 1: Business Details */}
                {step === 1 && (
                    <div className="space-y-6">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">Step 1: Organization</span>
                            <h2 className="text-2xl font-black text-white mt-1">Configure your Business Profile</h2>
                            <p className="text-xs text-slate-400 mt-1">Set your company identity, default reporting currency, and operating timezone.</p>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Company / Agency Name</label>
                                <input
                                    type="text"
                                    value={formData.company_name}
                                    onChange={e => setFormData({ ...formData, company_name: e.target.value })}
                                    className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="Acme Global Corporation"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">Currency</label>
                                    <select
                                        value={formData.currency}
                                        onChange={e => setFormData({ ...formData, currency: e.target.value })}
                                        className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="USD">USD ($)</option>
                                        <option value="EUR">EUR (€)</option>
                                        <option value="GBP">GBP (£)</option>
                                        <option value="INR">INR (₹)</option>
                                        <option value="CAD">CAD ($)</option>
                                        <option value="AUD">AUD ($)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">Timezone</label>
                                    <select
                                        value={formData.timezone}
                                        onChange={e => setFormData({ ...formData, timezone: e.target.value })}
                                        className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="UTC">UTC (GMT+0)</option>
                                        <option value="America/New_York">Eastern Time (US & Canada)</option>
                                        <option value="America/Los_Angeles">Pacific Time (US & Canada)</option>
                                        <option value="Europe/London">London (GMT+1)</option>
                                        <option value="Asia/Kolkata">India Standard Time (IST)</option>
                                        <option value="Asia/Tokyo">Tokyo (JST)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div className="pt-4 flex justify-end">
                            <button
                                onClick={() => handleSaveStep(2)}
                                disabled={loading || !formData.company_name}
                                className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all disabled:opacity-50"
                            >
                                <span>Continue</span>
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 2: Brand Profile */}
                {step === 2 && (
                    <div className="space-y-6">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">Step 2: Intelligence Context</span>
                            <h2 className="text-2xl font-black text-white mt-1">Define your Brand Identity</h2>
                            <p className="text-xs text-slate-400 mt-1">Mr. Fox uses this context to generate tone-aligned customer replies, proposals, and marketing content.</p>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Brand Description</label>
                                <textarea
                                    rows={3}
                                    value={formData.company_description}
                                    onChange={e => setFormData({ ...formData, company_description: e.target.value })}
                                    className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="We provide enterprise cloud architecture and automated workflow solutions for high-growth firms."
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Target Audience</label>
                                <input
                                    type="text"
                                    value={formData.target_audience}
                                    onChange={e => setFormData({ ...formData, target_audience: e.target.value })}
                                    className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="CTOs, Directors of Engineering, and B2B Operations Leaders"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Tone of Voice</label>
                                <select
                                    value={formData.tone_of_voice}
                                    onChange={e => setFormData({ ...formData, tone_of_voice: e.target.value })}
                                    className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="Professional, decisive, and helpful">Professional, decisive, and helpful (Recommended)</option>
                                    <option value="Warm, consultative, and reassuring">Warm, consultative, and reassuring</option>
                                    <option value="Concise, technical, and analytical">Concise, technical, and analytical</option>
                                    <option value="Energetic, modern, and punchy">Energetic, modern, and punchy</option>
                                </select>
                            </div>
                        </div>

                        <div className="pt-4 flex items-center justify-between">
                            <button
                                onClick={() => setStep(1)}
                                className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white flex items-center gap-1.5"
                            >
                                <ArrowLeft className="w-3.5 h-3.5" />
                                <span>Back</span>
                            </button>
                            <button
                                onClick={() => handleSaveStep(3)}
                                disabled={loading}
                                className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all"
                            >
                                <span>Continue</span>
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 3: Team Invitations */}
                {step === 3 && (
                    <div className="space-y-6">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">Step 3: Collaboration</span>
                            <h2 className="text-2xl font-black text-white mt-1">Invite your Teammates</h2>
                            <p className="text-xs text-slate-400 mt-1">Bring your team into your workspace with tenant-scoped role security.</p>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1.5">Teammate Email Addresses (Comma-separated)</label>
                            <textarea
                                rows={3}
                                value={formData.teammate_emails}
                                onChange={e => setFormData({ ...formData, teammate_emails: e.target.value })}
                                className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                                placeholder="alex@company.com, sarah@company.com"
                            />
                            <p className="text-[11px] text-slate-500 mt-1.5">You can also manage and assign granular roles later under Settings → Team.</p>
                        </div>

                        <div className="pt-4 flex items-center justify-between">
                            <button
                                onClick={() => setStep(2)}
                                className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white flex items-center gap-1.5"
                            >
                                <ArrowLeft className="w-3.5 h-3.5" />
                                <span>Back</span>
                            </button>
                            <button
                                onClick={() => handleSaveStep(4)}
                                disabled={loading}
                                className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all"
                            >
                                <span>{formData.teammate_emails ? 'Send Invites & Continue' : 'Skip & Continue'}</span>
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 4: Tool Connections */}
                {step === 4 && (
                    <div className="space-y-6">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">Step 4: Unified Communications</span>
                            <h2 className="text-2xl font-black text-white mt-1">Connect Communication Channels</h2>
                            <p className="text-xs text-slate-400 mt-1">Unify client interactions across Gmail, WhatsApp, and Slack into your Unified Inbox.</p>
                        </div>

                        <div className="space-y-3">
                            <div className="p-4 bg-slate-800/60 border border-slate-700/80 rounded-2xl flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold">G</div>
                                    <div>
                                        <h4 className="text-xs font-bold text-white">Gmail Business Account</h4>
                                        <p className="text-[11px] text-slate-400">Sync customer inquiries and send brand-aligned replies.</p>
                                    </div>
                                </div>
                                <span className="px-2.5 py-1 bg-slate-700/60 text-slate-300 rounded-lg text-[10px] font-semibold">Configured in Settings</span>
                            </div>

                            <div className="p-4 bg-slate-800/60 border border-slate-700/80 rounded-2xl flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">W</div>
                                    <div>
                                        <h4 className="text-xs font-bold text-white">WhatsApp Cloud API</h4>
                                        <p className="text-[11px] text-slate-400">Real-time messaging via Meta Cloud Business API.</p>
                                    </div>
                                </div>
                                <span className="px-2.5 py-1 bg-slate-700/60 text-slate-300 rounded-lg text-[10px] font-semibold">Configured in Settings</span>
                            </div>

                            <div className="p-4 bg-slate-800/60 border border-slate-700/80 rounded-2xl flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">S</div>
                                    <div>
                                        <h4 className="text-xs font-bold text-white">Slack Workspace</h4>
                                        <p className="text-[11px] text-slate-400">Internal alerts and client Slack Connect channels.</p>
                                    </div>
                                </div>
                                <span className="px-2.5 py-1 bg-slate-700/60 text-slate-300 rounded-lg text-[10px] font-semibold">Configured in Settings</span>
                            </div>
                        </div>

                        <div className="pt-4 flex items-center justify-between">
                            <button
                                onClick={() => setStep(3)}
                                className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white flex items-center gap-1.5"
                            >
                                <ArrowLeft className="w-3.5 h-3.5" />
                                <span>Back</span>
                            </button>
                            <button
                                onClick={() => handleSaveStep(5)}
                                disabled={loading}
                                className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all"
                            >
                                <span>Continue</span>
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 5: Mr. Fox Settings */}
                {step === 5 && (
                    <div className="space-y-6">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">Step 5: Executive AI</span>
                            <h2 className="text-2xl font-black text-white mt-1">Configure Mr. Fox Executive AI</h2>
                            <p className="text-xs text-slate-400 mt-1">Choose the primary intelligence model for automated briefings and multi-tool missions.</p>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1.5">Preferred AI Model</label>
                            <select
                                value={formData.default_model}
                                onChange={e => setFormData({ ...formData, default_model: e.target.value })}
                                className="w-full px-4 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"
                            >
                                <option value="gemini-1.5-pro">Gemini 1.5 Pro (Recommended: High context & precision)</option>
                                <option value="gpt-4o">OpenAI GPT-4o (High performance reasoning)</option>
                                <option value="claude-3-5-sonnet">Claude 3.5 Sonnet (Strong writing & analysis)</option>
                                <option value="llama-3.1-70b">Groq Llama 3.1 70B (Ultra-fast execution)</option>
                            </select>
                        </div>

                        <div className="p-4 bg-indigo-950/40 border border-indigo-800/60 rounded-2xl text-xs text-indigo-200">
                            <div className="font-bold flex items-center gap-1.5 mb-1 text-white">
                                <ShieldCheck className="w-4 h-4 text-emerald-400" />
                                <span>Human-in-the-Loop Safeguards Active</span>
                            </div>
                            <span>High-risk actions (sending emails, modifying invoices, approving leave) require explicit confirmation in the Unified Approval Center.</span>
                        </div>

                        <div className="pt-4 flex items-center justify-between">
                            <button
                                onClick={() => setStep(4)}
                                className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white flex items-center gap-1.5"
                            >
                                <ArrowLeft className="w-3.5 h-3.5" />
                                <span>Back</span>
                            </button>
                            <button
                                onClick={() => handleSaveStep(6)}
                                disabled={loading}
                                className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all"
                            >
                                <span>Continue</span>
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 6: Finish & Demo Data */}
                {step === 6 && (
                    <div className="space-y-6 text-center">
                        <div className="w-16 h-16 rounded-3xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto shadow-inner">
                            <CheckCircle2 className="w-8 h-8" />
                        </div>

                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-emerald-400">All Set</span>
                            <h2 className="text-2xl font-black text-white mt-1">Your Workspace is Ready</h2>
                            <p className="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                                You are ready to launch into the Executive Command Center. Would you like to load sample demo records for an immediate tour?
                            </p>
                        </div>

                        <div className="p-5 bg-slate-800/50 border border-slate-700/80 rounded-2xl flex items-center justify-between text-left">
                            <div>
                                <h4 className="text-xs font-bold text-white flex items-center gap-1.5">
                                    <Sparkles className="w-3.5 h-3.5 text-indigo-400" />
                                    <span>Load Demo Workspace Data</span>
                                </h4>
                                <p className="text-[11px] text-slate-400 mt-0.5">Generates sample leads, invoices, low stock items, and customer chats to explore health signals.</p>
                            </div>
                            <button
                                onClick={handleLoadDemo}
                                disabled={loading || demoLoaded}
                                className={`px-4 py-2 rounded-xl text-xs font-bold transition-all ${
                                    demoLoaded ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-slate-700 hover:bg-slate-600 text-white'
                                }`}
                            >
                                {demoLoaded ? 'Demo Loaded' : 'Load Demo Data'}
                            </button>
                        </div>

                        <div className="pt-4">
                            <button
                                onClick={handleComplete}
                                disabled={loading}
                                className="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl text-sm font-bold shadow-xl shadow-indigo-600/30 flex items-center justify-center gap-2 transition-all"
                            >
                                <span>Launch Executive Command Center</span>
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Footer */}
            <div className="max-w-4xl mx-auto w-full text-center text-[11px] text-slate-500 pt-8">
                HiddenLeaf Agency • Enterprise Operating System & Intelligence Engine
            </div>
        </div>
    );
}
