import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card, MetricCard } from '@hiddenleaf/ui/Card';

export default function TrainingShow() {
  const { program = {}, report = {}, employees = [], flash = {} } = usePage<any>().props;
  const [employeeId, setEmployeeId] = useState('');

  const handleEnroll = (e: React.FormEvent) => {
    e.preventDefault();
    router.post(`/hrm/training/${program.id}/enroll`, { employee_id: employeeId }, {
      onSuccess: () => setEmployeeId(''),
    });
  };

  const handleOutcome = (enrollmentId: number, passed: boolean) => {
    router.post(`/hrm/training/${program.id}/enrollments/${enrollmentId}/outcome`, {
      score: 85,
      passed: passed,
    });
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title={`HRM ? Training: ${program.title}`} />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div>
        <Link href="/hrm/training" className="text-xs text-indigo-400 hover:underline">? Back to Programs</Link>
        <h1 className="text-2xl font-bold text-white tracking-tight">{program.title}</h1>
        <p className="text-sm text-slate-400">Category: {program.category} ? Status: <span className="uppercase font-semibold text-indigo-400">{program.status}</span></p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <MetricCard label="Enrolled" value={report.enrolled_count ?? 0} />
        <MetricCard label="Passed" value={report.passed_count ?? 0} />
        <MetricCard label="Average Score" value={report.avg_score ?? 0} />
        <MetricCard label="Completion Rate" value={`${report.completion_rate ?? 0}%`} />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card title="Enroll Employee" subtitle="Add participant to program">
          <form onSubmit={handleEnroll} className="space-y-4">
            <select
              className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={employeeId}
              onChange={e => setEmployeeId(e.target.value)}
              required
            >
              <option value="">Select Employee</option>
              {employees.map((e: any) => <option key={e.id} value={e.id}>{e.name}</option>)}
            </select>
            <Button type="submit" variant="primary">Enroll Participant</Button>
          </form>
        </Card>

        <div className="md:col-span-2">
          <Card title="Enrolled Participants" subtitle="Manage outcomes and completions">
            <div className="space-y-2 pt-2">
              {(program.enrollments || []).map((enr: any) => (
                <div key={enr.id} className="flex items-center justify-between p-3 rounded bg-slate-900 border border-slate-800">
                  <div>
                    <div className="text-sm font-semibold text-white">{enr.employee?.name}</div>
                    <div className="text-xs text-slate-400">Status: {enr.status} {enr.score ? `? Score: ${enr.score}` : ''}</div>
                  </div>
                  {enr.status !== 'completed' && (
                    <div className="flex gap-2">
                      <Button variant="primary" size="sm" onClick={() => handleOutcome(enr.id, true)}>Pass</Button>
                      <Button variant="danger" size="sm" onClick={() => handleOutcome(enr.id, false)}>Fail</Button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
