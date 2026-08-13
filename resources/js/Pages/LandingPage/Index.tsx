import React from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { 
  Sparkles, 
  ShieldCheck, 
  Layers, 
  ArrowRight, 
  CheckCircle2, 
  Package, 
  Users, 
  FileText, 
  DollarSign, 
  Headphones,
  Bot
} from 'lucide-react';

export default function LandingPageIndex({ plans = [] }: { plans?: any[] }) {
  return (
    <div className="min-h-screen bg-[#060709] text-gray-100 antialiased overflow-x-hidden selection:bg-purple-600 selection:text-white">
      {/* Subtle Ambient Background */}
      <div className="fixed inset-0 pointer-events-none">
        <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-b from-purple-900/15 via-violet-900/5 to-transparent blur-3xl" />
        <div className="absolute top-1/3 left-10 w-96 h-96 bg-cyan-900/10 rounded-full blur-3xl" />
        <div className="absolute bottom-20 right-10 w-96 h-96 bg-indigo-900/10 rounded-full blur-3xl" />
      </div>

      {/* Navigation Header */}
      <header className="sticky top-0 z-50 glass-1 border-b border-white/10 px-6 sm:px-12 py-4 flex items-center justify-between">
        <Link href="/" className="flex items-center gap-2.5">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-md">
            HL
          </div>
          <div>
            <span className="text-sm font-bold tracking-tight text-white block">HiddenLeaf</span>
            <span className="text-[10px] font-medium text-violet-400 block -mt-1">BusinessOS 2026</span>
          </div>
        </Link>

        <div className="flex items-center gap-3">
          <Link href="/login">
            <Button variant="ghost" size="sm">
              Sign In
            </Button>
          </Link>
          <Link href="/register">
            <Button variant="intelligence" size="sm" icon={<ArrowRight className="w-3.5 h-3.5" />} iconPosition="right">
              Get Started
            </Button>
          </Link>
        </div>
      </header>

      {/* Hero Section */}
      <section className="relative pt-24 pb-20 px-6 max-w-5xl mx-auto text-center space-y-6">
        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-2 border border-purple-500/30 text-purple-300 text-xs font-semibold shadow-md animate-in fade-in zoom-in duration-300">
          <Sparkles className="w-3.5 h-3.5 animate-fox-pulse" />
          <span>Next-Generation Operating System for Business</span>
        </div>

        <h1 className="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-[1.1]">
          Run Your Entire Enterprise on <span className="bg-gradient-to-r from-violet-400 via-purple-300 to-cyan-300 bg-clip-text text-transparent">Autopilot</span>.
        </h1>

        <p className="text-base sm:text-lg text-gray-400 max-w-2xl mx-auto leading-relaxed">
          The AI-native BusinessOS with multi-workspace tenant isolation, unalterable ledger audits, CRM, procurement, helpdesk, and Mr Fox proactive copilot.
        </p>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-3.5 pt-4">
          <Link href="/register">
            <Button variant="intelligence" size="lg" icon={<ArrowRight className="w-4 h-4" />} iconPosition="right" className="shadow-2xl">
              Launch Your Workspace
            </Button>
          </Link>
          <Link href="/login">
            <Button variant="secondary" size="lg">
              Live Demo Access
            </Button>
          </Link>
        </div>
      </section>

      {/* 3 Core Architecture Pillars */}
      <section className="px-6 py-12 max-w-6xl mx-auto">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card level={1} className="space-y-3 p-6">
            <div className="w-10 h-10 rounded-xl bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-300">
              <ShieldCheck className="w-5 h-5" />
            </div>
            <h3 className="text-base font-bold text-white tracking-tight">Deterministic Multi-Tenancy</h3>
            <p className="text-xs text-gray-400 leading-relaxed">
              Cryptographically verified organization & workspace scoping prevents data leakage with strict RBAC ceilings.
            </p>
          </Card>

          <Card level={2} className="space-y-3 p-6 border-purple-500/30">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-purple-600 flex items-center justify-center text-white shadow-md">
              <Sparkles className="w-5 h-5 animate-fox-pulse" />
            </div>
            <h3 className="text-base font-bold text-white tracking-tight">Mr Fox Autonomous Copilot</h3>
            <p className="text-xs text-gray-300 leading-relaxed">
              Proactive business intelligence monitoring cash flow, overdue receivables, and risk approvals in real time.
            </p>
          </Card>

          <Card level={1} className="space-y-3 p-6">
            <div className="w-10 h-10 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-emerald-300">
              <Package className="w-5 h-5" />
            </div>
            <h3 className="text-base font-bold text-white tracking-tight">7 Bundled Core Engines</h3>
            <p className="text-xs text-gray-400 leading-relaxed">
              HRM, Accounting, Lead Pipeline, Task Management, POS, Inventory Warehouses, and Customer Helpdesk in one unified platform.
            </p>
          </Card>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-white/10 mt-20 py-8 px-6 text-center text-xs text-gray-500">
        <p>© 2026 HiddenLeaf Business Operating System. All rights reserved.</p>
      </footer>
    </div>
  );
}
