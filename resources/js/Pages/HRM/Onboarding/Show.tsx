import React from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';

export default function OnboardingShow() {
  const { onboarding = {}, progress = 0, flash = {} } = usePage<any>().props;

  const handleCompleteTask = (taskId: number) => {
    router.post(`/hrm/onboarding/${onboarding.id}/tasks/${taskId}/complete`);
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title={`HRM ? Onboarding: ${onboarding.employee?.name || 'Checklist'}`} />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div>
        <Link href="/hrm/onboarding" className="text-xs text-indigo-400 hover:underline">? Back to Onboardings</Link>
        <h1 className="text-2xl font-bold text-white tracking-tight">Onboarding Checklist: {onboarding.employee?.name}</h1>
        <p className="text-sm text-slate-400">Progress: <span className="font-semibold text-emerald-400">{progress}%</span> ? Status: <span className="uppercase text-indigo-400">{onboarding.status}</span></p>
      </div>

      <div className="w-full bg-slate-900 rounded-full h-3 border border-slate-800 overflow-hidden">
        <div className="bg-indigo-500 h-full transition-all duration-300" style={{ width: `${progress}%` }} />
      </div>

      <Card title="Task Checklist" subtitle="Complete required onboarding milestones">
        <div className="space-y-3 pt-2">
          {(onboarding.tasks || []).map((t: any) => (
            <div key={t.id} className="flex items-center justify-between p-3 rounded bg-slate-900 border border-slate-800">
              <div>
                <div className={`text-sm font-semibold ${t.status === 'completed' ? 'line-through text-slate-500' : 'text-white'}`}>
                  {t.title}
                </div>
                <div className="text-xs text-slate-400">Category: {t.category} ? Due: {t.due_on || 'N/A'}</div>
              </div>
              {t.status !== 'completed' ? (
                <Button variant="primary" size="sm" onClick={() => handleCompleteTask(t.id)}>
                  Mark Done
                </Button>
              ) : (
                <span className="text-xs font-semibold text-emerald-400 uppercase">Completed</span>
              )}
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
