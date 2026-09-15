import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function PayrollIndex() {
  const { runs = { data: [] }, components = [], recentPayslips = [], flash = {} } = usePage<any>().props;
  const [showRunModal, setShowRunModal] = useState(false);
  const [runData, setRunData] = useState({ period_start: '', period_end: '' });

  const handleCreateRun = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/payroll/runs', runData);
  };

  const columns = [
    { header: 'Run Number', accessor: 'run_number' as const },
    { header: 'Period Start', accessor: 'period_start' as const },
    { header: 'Period End', accessor: 'period_end' as const },
    { header: 'Employees', accessor: 'employee_count' as const },
    { header: 'Total Net', accessor: 'total_net' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any) => (
        <Link href={`/hrm/payroll/runs/${id}`}>
          <Button variant="ghost" size="sm">Manage Run</Button>
        </Link>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Payroll" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Payroll & Salary Runs</h1>
          <p className="text-sm text-slate-400">Generate, approve, and disburse monthly compensation.</p>
        </div>
        <Button variant="primary" size="sm" onClick={() => setShowRunModal(!showRunModal)}>
          New Payroll Run
        </Button>
      </div>

      {showRunModal && (
        <Card title="Start New Payroll Run" subtitle="Specify pay period">
          <form onSubmit={handleCreateRun} className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input
              type="date"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={runData.period_start}
              onChange={e => setRunData({ ...runData, period_start: e.target.value })}
              required
            />
            <input
              type="date"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={runData.period_end}
              onChange={e => setRunData({ ...runData, period_end: e.target.value })}
              required
            />
            <Button type="submit" variant="primary">Create Run</Button>
          </form>
        </Card>
      )}

      <Card title="Payroll Runs History">
        <Table columns={columns} data={runs.data || []} emptyMessage="No payroll runs executed yet." />
      </Card>
    </div>
  );
}
