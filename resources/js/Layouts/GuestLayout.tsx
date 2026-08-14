import React from 'react';
import { Head } from '@inertiajs/react';
import { Leaf, ShieldCheck } from 'lucide-react';

interface GuestLayoutProps {
  title: string;
  eyebrow?: string;
  description?: string;
  children: React.ReactNode;
}

export default function GuestLayout({ title, eyebrow = 'HiddenLeaf BusinessOS', description, children }: GuestLayoutProps) {
  return (
    <div className="relative min-h-screen overflow-hidden bg-[#07090d] text-slate-100">
      <Head title={title} />
      <div className="pointer-events-none absolute inset-0" aria-hidden="true">
        <div className="absolute -left-32 top-[-12rem] h-[32rem] w-[32rem] rounded-full bg-emerald-500/[0.08] blur-[110px]" />
        <div className="absolute -right-24 bottom-[-15rem] h-[36rem] w-[36rem] rounded-full bg-violet-600/[0.10] blur-[130px]" />
        <div className="absolute inset-0 opacity-[0.025] [background-image:linear-gradient(rgba(255,255,255,.4)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.4)_1px,transparent_1px)] [background-size:48px_48px]" />
      </div>

      <main className="relative mx-auto flex min-h-screen w-full max-w-7xl items-center px-4 py-8 sm:px-6 lg:px-10">
        <div className="grid w-full items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(380px,480px)]">
          <section className="hidden max-w-xl lg:block" aria-label="HiddenLeaf introduction">
            <div className="mb-8 inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 shadow-2xl shadow-black/20 backdrop-blur-xl">
              <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-500 text-slate-950 shadow-lg shadow-emerald-500/20">
                <Leaf className="h-5 w-5" aria-hidden="true" />
              </span>
              <div>
                <p className="font-semibold tracking-tight text-white">HiddenLeaf BusinessOS</p>
                <p className="text-xs text-slate-400">Secure business operations, in one workspace.</p>
              </div>
            </div>
            <h2 className="text-4xl font-semibold leading-tight tracking-[-0.04em] text-white xl:text-5xl">
              Clarity for every team. Control for every workspace.
            </h2>
            <p className="mt-5 max-w-lg text-base leading-7 text-slate-400">
              Run finance, people, sales, projects, inventory, and service operations from a secure multi-tenant platform.
            </p>
            <div className="mt-8 flex items-center gap-3 text-sm text-slate-400">
              <ShieldCheck className="h-5 w-5 text-emerald-400" aria-hidden="true" />
              Tenant isolation and role-based access are enforced by the platform.
            </div>
          </section>

          <section className="mx-auto w-full max-w-[480px]">
            <div className="mb-6 text-center lg:text-left">
              <div className="mb-5 inline-flex items-center gap-2 lg:hidden">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-500 text-slate-950">
                  <Leaf className="h-5 w-5" aria-hidden="true" />
                </span>
                <span className="font-semibold text-white">HiddenLeaf BusinessOS</span>
              </div>
              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400">{eyebrow}</p>
              <h1 className="mt-2 text-3xl font-semibold tracking-[-0.035em] text-white">{title}</h1>
              {description && <p className="mt-2 text-sm leading-6 text-slate-400">{description}</p>}
            </div>
            {children}
          </section>
        </div>
      </main>
    </div>
  );
}
