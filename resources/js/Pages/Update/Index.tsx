import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { RefreshCw, CheckCircle2, ShieldCheck, ArrowRight } from 'lucide-react';

export default function UpdateIndex({ currentVersion = '1.0.0', targetVersion = '1.1.0' }: any) {
  const { post, processing } = useForm();

  const handleRunUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    post('/update/run');
  };

  return (
    <div className="min-h-screen bg-[#060709] text-gray-100 flex items-center justify-center p-4 relative overflow-hidden">
      <div className="max-w-md w-full relative z-10 space-y-6">
        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white mx-auto shadow-xl">
            <RefreshCw className="w-6 h-6 animate-spin-slow" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">System Schema Updater</h1>
          <p className="text-xs text-gray-400">Upgrade database schemas, indexes, and clear system caches</p>
        </div>

        <Card level={1} className="p-6 space-y-4">
          <div className="p-3 rounded-lg bg-white/[0.03] border border-white/10 flex items-center justify-between text-xs">
            <span className="text-gray-400">Current Build:</span>
            <span className="font-mono text-white font-bold">v{currentVersion}</span>
          </div>

          <div className="p-3 rounded-lg bg-violet-600/10 border border-violet-500/20 flex items-center justify-between text-xs">
            <span className="text-violet-300">Target Upgrade:</span>
            <span className="font-mono text-violet-200 font-bold">v{targetVersion}</span>
          </div>

          <form onSubmit={handleRunUpdate} className="pt-2">
            <Button
              type="submit"
              variant="primary"
              size="lg"
              loading={processing}
              className="w-full"
              icon={<ShieldCheck className="w-4 h-4" />}
            >
              Execute Database Migrations
            </Button>
          </form>

          <div className="pt-4 border-t border-white/10 text-center">
            <Link href="/dashboard" className="text-xs text-gray-400 hover:text-white">
              Return to Executive Dashboard
            </Link>
          </div>
        </Card>
      </div>
    </div>
  );
}
