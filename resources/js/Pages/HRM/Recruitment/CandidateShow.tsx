import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';

export default function CandidateShow() {
  const { candidate = {}, flash = {} } = usePage<any>().props;
  const [stage, setStage] = useState(candidate.stage || '');
  const [rejectReason, setRejectReason] = useState('');

  const nextStages: Record<string, string[]> = {
    applied: ['screening', 'rejected'],
    screening: ['interview', 'rejected'],
    interview: ['assessment', 'rejected'],
    assessment: ['offer', 'rejected'],
    offer: ['hired', 'rejected'],
    hired: ['rejected'],
  };

  const handleStageMove = (newStage: string) => {
    router.post(`/hrm/recruitment/candidates/${candidate.id}/stage`, { stage: newStage });
  };

  const handleReject = (e: React.FormEvent) => {
    e.preventDefault();
    router.post(`/hrm/recruitment/candidates/${candidate.id}/reject`, { reason: rejectReason });
  };

  const handleConvert = () => {
    if (confirm('Convert this hired candidate to an active employee?')) {
      router.post(`/hrm/recruitment/candidates/${candidate.id}/convert`, {
        basic_salary: candidate.expected_salary || 0,
      });
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title={`HRM ? Candidate: ${candidate.name}`} />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div>
        <Link href="/hrm/recruitment" className="text-xs text-indigo-400 hover:underline">? Back to Pipeline</Link>
        <h1 className="text-2xl font-bold text-white tracking-tight">{candidate.name}</h1>
        <p className="text-sm text-slate-400">{candidate.email} ? Stage: <span className="font-semibold text-indigo-400 uppercase">{candidate.stage}</span></p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card title="Candidate Information" subtitle="Applicant background">
          <div className="space-y-3 pt-2 text-sm">
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Position</span>
              <span>{candidate.position?.title || 'General'}</span>
            </div>
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Current Company</span>
              <span>{candidate.current_company || 'N/A'}</span>
            </div>
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Expected Salary</span>
              <span>{candidate.expected_salary ?? 'N/A'}</span>
            </div>
          </div>
        </Card>

        <Card title="Pipeline Progression" subtitle="Advance or reject candidate">
          <div className="space-y-4">
            <div>
              <label className="block text-xs text-slate-400 mb-2">Advance to Next Stage</label>
              <div className="flex gap-2 flex-wrap">
                {(nextStages[candidate.stage] || []).map((s: string) => (
                  <Button
                    key={s}
                    variant={s === 'rejected' ? 'danger' : 'primary'}
                    size="sm"
                    onClick={() => handleStageMove(s)}
                  >
                    Move to {s}
                  </Button>
                ))}
              </div>
            </div>

            {candidate.stage === 'hired' && candidate.status !== 'converted' && (
              <div className="pt-4 border-t border-slate-800">
                <Button variant="primary" size="sm" onClick={handleConvert}>
                  Convert to Employee Record
                </Button>
              </div>
            )}

            {candidate.stage !== 'rejected' && (
              <form onSubmit={handleReject} className="pt-4 border-t border-slate-800 space-y-2">
                <label className="block text-xs text-rose-400">Reject Candidate</label>
                <div className="flex gap-2">
                  <input
                    placeholder="Rejection reason"
                    className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white flex-1"
                    value={rejectReason}
                    onChange={e => setRejectReason(e.target.value)}
                    required
                  />
                  <Button type="submit" variant="danger" size="sm">Reject</Button>
                </div>
              </form>
            )}
          </div>
        </Card>
      </div>
    </div>
  );
}
