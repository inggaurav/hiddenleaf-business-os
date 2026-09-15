import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';

export default function DisciplinaryShow() {
  const { case: discCase = {}, flash = {} } = usePage<any>().props;
  const [actionTaken, setActionTaken] = useState(discCase.action_taken || '');
  const [actionNotes, setActionNotes] = useState(discCase.action_notes || '');

  const handleRecordAction = (e: React.FormEvent) => {
    e.preventDefault();
    router.post(`/hrm/disciplinary/${discCase.id}/action`, {
      action_taken: actionTaken,
      action_notes: actionNotes,
    });
  };

  const handleClose = () => {
    if (confirm('Close this disciplinary case?')) {
      router.post(`/hrm/disciplinary/${discCase.id}/close`);
    }
  };

  const handleReopen = () => {
    router.post(`/hrm/disciplinary/${discCase.id}/reopen`);
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title={`HRM ? Case: ${discCase.case_number}`} />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <Link href="/hrm/disciplinary" className="text-xs text-indigo-400 hover:underline">? Back to Cases</Link>
          <h1 className="text-2xl font-bold text-white tracking-tight">Case: {discCase.case_number}</h1>
          <p className="text-sm text-slate-400">{discCase.employee?.name} ? Severity: <span className="uppercase text-rose-400 font-semibold">{discCase.severity}</span> ? Status: <span className="uppercase font-semibold text-indigo-400">{discCase.status}</span></p>
        </div>
        <div>
          {discCase.status === 'open' ? (
            <Button variant="outline" size="sm" onClick={handleClose}>Close Case</Button>
          ) : (
            <Button variant="primary" size="sm" onClick={handleReopen}>Reopen Case</Button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card title="Incident Details" subtitle="Original incident report">
          <div className="space-y-3 pt-2 text-sm">
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Date</span>
              <span>{discCase.incident_date}</span>
            </div>
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Type</span>
              <span>{discCase.type}</span>
            </div>
            <div className="pt-2">
              <span className="text-slate-400 block mb-1">Description</span>
              <p className="text-slate-300 whitespace-pre-wrap">{discCase.incident_description}</p>
            </div>
          </div>
        </Card>

        <Card title="Corrective Action" subtitle="Record formal disciplinary outcome">
          {discCase.status === 'open' ? (
            <form onSubmit={handleRecordAction} className="space-y-3">
              <input
                placeholder="Action Taken (e.g. Written Warning)"
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={actionTaken}
                onChange={e => setActionTaken(e.target.value)}
                required
              />
              <textarea
                placeholder="Action Notes & Timeline"
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                rows={3}
                value={actionNotes}
                onChange={e => setActionNotes(e.target.value)}
                required
              />
              <Button type="submit" variant="danger" size="sm">Record Action</Button>
            </form>
          ) : (
            <div className="space-y-2 text-sm">
              <div>Action: <strong className="text-white">{discCase.action_taken || 'None'}</strong></div>
              <div>Notes: <p className="text-slate-400">{discCase.action_notes || 'None'}</p></div>
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}
