import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function ExitIndex() {
  const { exits = { data: [] }, employees = [], flash = {} } = usePage<any>().props;
  const [showInitiate, setShowInitiate] = useState(false);
  const [formData, setFormData] = useState({
    employee_id: '',
    exit_reason: 'Resignation',
    exit_date: new Date().toISOString().split('T')[0],
  });

  const handleInitiate = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/exit/initiate', formData, {
      onSuccess: () => setShowInitiate(false),
    });
  };

  const handleInterview = (empId: number) => {
    const reason = prompt('Reason for exit:');
    if (reason) {
      router.post(`/hrm/exit/${empId}/interview`, { exit_reason: reason });
    }
  };

  const handleComplete = (empId: number) => {
    if (confirm('Complete formal exit and terminate employee status?')) {
      router.post(`/hrm/exit/${empId}/complete`);
    }
  };

  const columns = [
    { header: 'Employee', accessor: 'name' as const },
    { header: 'Exit Date', accessor: 'exit_date' as const },
    { header: 'Reason', accessor: 'exit_reason' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any, row: any) => (
        <div className="flex gap-2">
          {!row.exit_interview && (
            <Button variant="secondary" size="sm" onClick={() => handleInterview(id)}>Interview</Button>
          )}
          <Link href={`/hrm/exit/${id}/clearance`}>
            <Button variant="ghost" size="sm">Clearance</Button>
          </Link>
          {row.status !== 'terminated' && (
            <Button variant="danger" size="sm" onClick={() => handleComplete(id)}>Complete Exit</Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Exit" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Exit & Offboarding Management</h1>
          <p className="text-sm text-slate-400">Exit interviews, departmental clearances, and handover completion.</p>
        </div>
        <Button variant="primary" size="sm" onClick={() => setShowInitiate(!showInitiate)}>
          Initiate Exit
        </Button>
      </div>

      {showInitiate && (
        <Card title="Initiate Employee Separation" subtitle="Schedule exit date and start clearance checklist">
          <form onSubmit={handleInitiate} className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <select
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.employee_id}
              onChange={e => setFormData({ ...formData, employee_id: e.target.value })}
              required
            >
              <option value="">Select Employee</option>
              {employees.map((e: any) => <option key={e.id} value={e.id}>{e.name}</option>)}
            </select>
            <input
              placeholder="Exit Reason"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.exit_reason}
              onChange={e => setFormData({ ...formData, exit_reason: e.target.value })}
              required
            />
            <input
              type="date"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.exit_date}
              onChange={e => setFormData({ ...formData, exit_date: e.target.value })}
              required
            />
            <div className="md:col-span-3 flex justify-end">
              <Button type="submit" variant="primary">Start Exit Process</Button>
            </div>
          </form>
        </Card>
      )}

      <Card title="Separation Records">
        <Table columns={columns} data={exits.data || []} emptyMessage="No employee exits recorded." />
      </Card>
    </div>
  );
}
