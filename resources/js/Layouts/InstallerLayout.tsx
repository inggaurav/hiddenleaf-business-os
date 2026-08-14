import React from 'react';
import { Head } from '@inertiajs/react';
import { Check, Circle, Leaf, LockKeyhole, ShieldCheck } from 'lucide-react';

export interface InstallerStep {
  label: string;
  shortLabel: string;
}

interface InstallerLayoutProps {
  steps: InstallerStep[];
  currentStep: number;
  children: React.ReactNode;
}

export default function InstallerLayout({ steps, currentStep, children }: InstallerLayoutProps) {
  return (
    <div className="min-h-screen bg-[#07090d] text-slate-100">
      <Head title="Secure Installation" />
      <div className="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div className="absolute -left-40 -top-48 h-[34rem] w-[34rem] rounded-full bg-emerald-500/[0.09] blur-[120px]" />
        <div className="absolute -bottom-56 -right-32 h-[38rem] w-[38rem] rounded-full bg-violet-600/[0.10] blur-[140px]" />
        <div className="absolute inset-0 opacity-[0.025] [background-image:linear-gradient(rgba(255,255,255,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.35)_1px,transparent_1px)] [background-size:52px_52px]" />
      </div>

      <header className="relative border-b border-white/[0.07] bg-[#090c11]/80 backdrop-blur-xl">
        <div className="mx-auto flex max-w-[1480px] items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-10">
          <div className="flex min-w-0 items-center gap-3">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-cyan-500 text-slate-950 shadow-lg shadow-emerald-500/20">
              <Leaf className="h-5 w-5" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <p className="truncate font-semibold tracking-tight text-white">HiddenLeaf BusinessOS</p>
              <p className="text-xs text-slate-400">Secure Installation</p>
            </div>
          </div>
          <div className="hidden items-center gap-2 rounded-full border border-emerald-400/15 bg-emerald-400/[0.06] px-3 py-1.5 text-xs font-medium text-emerald-300 sm:flex">
            <LockKeyhole className="h-3.5 w-3.5" aria-hidden="true" />
            Local setup session
          </div>
        </div>
      </header>

      <main className="relative mx-auto grid max-w-[1480px] gap-5 px-3 py-4 sm:px-6 sm:py-7 lg:grid-cols-[280px_minmax(0,1fr)] lg:gap-7 lg:px-10">
        <aside className="rounded-2xl border border-white/[0.08] bg-white/[0.035] p-3 backdrop-blur-xl lg:sticky lg:top-7 lg:h-fit lg:p-5" aria-label="Installation progress">
          <div className="mb-4 hidden lg:block">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Installation progress</p>
            <p className="mt-1 text-sm text-slate-300">Step {currentStep + 1} of {steps.length}</p>
          </div>

          <ol className="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-1 lg:overflow-visible" aria-label="Installer steps">
            {steps.map((step, index) => {
              const complete = index < currentStep;
              const active = index === currentStep;
              return (
                <li key={step.label} className="min-w-[116px] lg:min-w-0">
                  <div
                    aria-current={active ? 'step' : undefined}
                    className={`flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition-colors lg:border-transparent ${
                      active
                        ? 'border-emerald-400/30 bg-emerald-400/[0.10] text-white'
                        : complete
                          ? 'border-white/[0.06] bg-white/[0.025] text-slate-300'
                          : 'border-white/[0.05] text-slate-500'
                    }`}
                  >
                    <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full border ${active ? 'border-emerald-300 bg-emerald-400 text-slate-950' : complete ? 'border-emerald-400/40 bg-emerald-400/10 text-emerald-300' : 'border-white/15 text-slate-600'}`}>
                      {complete ? <Check className="h-3.5 w-3.5" aria-hidden="true" /> : active ? <Circle className="h-2.5 w-2.5 fill-current" aria-hidden="true" /> : <span className="text-[10px] font-semibold">{index + 1}</span>}
                    </span>
                    <span className="hidden lg:inline">{step.label}</span>
                    <span className="lg:hidden">{step.shortLabel}</span>
                  </div>
                </li>
              );
            })}
          </ol>

          <div className="mt-6 hidden rounded-xl border border-white/[0.07] bg-black/10 p-4 lg:block">
            <ShieldCheck className="h-5 w-5 text-emerald-400" aria-hidden="true" />
            <p className="mt-3 text-xs font-medium text-slate-300">Secrets stay private</p>
            <p className="mt-1 text-xs leading-5 text-slate-500">Passwords and entitlement tokens are never included in the final summary.</p>
          </div>
        </aside>

        <section className="min-w-0 rounded-2xl border border-white/[0.08] bg-[#0c1016]/90 shadow-2xl shadow-black/25 backdrop-blur-xl">
          {children}
        </section>
      </main>
    </div>
  );
}
