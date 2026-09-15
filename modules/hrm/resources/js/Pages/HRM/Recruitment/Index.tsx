import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function RecruitmentIndex() {
  const { positions = [], candidates = { data: [] }, pipelineSummary = {}, departments = [], flash = {} } = usePage<any>().props;
  const [showPosModal, setShowPosModal] = useState(false);
  const [showCandModal, setShowCandModal] = useState(false);

  const [posData, setPosData] = useState({ title: '', department_id: '', openings: 1, salary_min: '', salary_max: '' });
  const [candData, setCandData] = useState({ name: '', email: '', phone: '', job_position_id: '', current_company: '', expected_salary: '' });

  const handleCreatePosition = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/recruitment/positions', posData, {
      onSuccess: () => setShowPosModal(false),
    });
  };

  const handleCreateCandidate = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/recruitment/candidates', candData, {
      onSuccess: () => setShowCandModal(false),
    });
  };

  const candidateColumns = [
    { header: 'Name', accessor: 'name' as const },
    { header: 'Email', accessor: 'email' as const },
    {
      header: 'Position',
      accessor: 'position' as const,
      render: (pos: any) => pos?.title || 'General Applicant',
    },
    { header: 'Stage', accessor: 'stage' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any) => (
        <Link href={`/hrm/recruitment/candidates/${id}`}>
          <Button variant="ghost" size="sm">Review</Button>
        </Link>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Recruitment" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Recruitment & ATS Pipeline</h1>
          <p className="text-sm text-slate-400">Track candidate progression from application to hire.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" size="sm" onClick={() => setShowPosModal(!showPosModal)}>
            New Position
          </Button>
          <Button variant="primary" size="sm" onClick={() => setShowCandModal(!showCandModal)}>
            Add Candidate
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
        {['applied', 'screening', 'interview', 'assessment', 'offer', 'hired', 'rejected'].map(stage => (
          <div key={stage} className="p-3 bg-slate-900 rounded border border-slate-800 text-center">
            <div className="text-xs uppercase text-slate-400 tracking-wider font-semibold">{stage}</div>
            <div className="text-xl font-bold text-white mt-1">{pipelineSummary[stage] ?? 0}</div>
          </div>
        ))}
      </div>

      {showPosModal && (
        <Card title="Post Open Job Position" subtitle="Define role details and openings">
          <form onSubmit={handleCreatePosition} className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input
              placeholder="Position Title"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={posData.title}
              onChange={e => setPosData({ ...posData, title: e.target.value })}
              required
            />
            <select
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={posData.department_id}
              onChange={e => setPosData({ ...posData, department_id: e.target.value })}
            >
              <option value="">Department</option>
              {departments.map((d: any) => <option key={d.id} value={d.id}>{d.name}</option>)}
            </select>
            <input
              type="number"
              placeholder="Openings"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={posData.openings}
              onChange={e => setPosData({ ...posData, openings: parseInt(e.target.value) || 1 })}
            />
            <Button type="submit" variant="primary">Create Position</Button>
          </form>
        </Card>
      )}

      {showCandModal && (
        <Card title="Register Candidate" subtitle="Add applicant profile to pipeline">
          <form onSubmit={handleCreateCandidate} className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input
              placeholder="Full Name"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={candData.name}
              onChange={e => setCandData({ ...candData, name: e.target.value })}
              required
            />
            <input
              type="email"
              placeholder="Email"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={candData.email}
              onChange={e => setCandData({ ...candData, email: e.target.value })}
              required
            />
            <input
              placeholder="Phone"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={candData.phone}
              onChange={e => setCandData({ ...candData, phone: e.target.value })}
            />
            <select
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={candData.job_position_id}
              onChange={e => setCandData({ ...candData, job_position_id: e.target.value })}
            >
              <option value="">Select Position</option>
              {positions.map((p: any) => <option key={p.id} value={p.id}>{p.title}</option>)}
            </select>
            <input
              placeholder="Expected Salary"
              type="number"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={candData.expected_salary}
              onChange={e => setCandData({ ...candData, expected_salary: e.target.value })}
            />
            <Button type="submit" variant="primary">Save Candidate</Button>
          </form>
        </Card>
      )}

      <Card title="Candidate Pipeline">
        <Table columns={candidateColumns} data={candidates.data || []} emptyMessage="No candidates registered." />
      </Card>
    </div>
  );
}
