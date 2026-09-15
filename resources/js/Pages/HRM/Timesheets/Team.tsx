import React from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function TimesheetsTeam() {
  const { from = '', to = '', summary = [], pendingTimesheets = [], flash = {} } = usePage<any>().props;

  const handleApprove = (id: number) => {
    router.post(`/hrm/timesheets/${id}/approve`);
  };

  const handleReject = (id: number) => {
    const reason = prompt('Reason for rejection:');
    if (reason) {
      router.post(`/hrm/timesheets/${id}/reject`, { reason });
    }
  };

  const pendingColumns = [
    {
      header: 'Employee',
      accessor: 'employee' as const,
      render: (emp: any) => emp?.name || 'N/A',
    },
    { header: 'Date', accessor: 'work_date' as const },
    { header: 'Hours', accessor: 'hours' as const },
    { header: 'Project', accessor: 'project_code' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any) => (
        <div className="flex gap-2">
          <Button variant="primary" size="sm" onClick={() => handleApprove(id)}>Approve</Button>
          <Button variant="danger" size="sm" onClick={() => handleReject(id)}>Reject</Button>
        </div>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Team Timesheets" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div>
        <Link href="/hrm/timesheets" className="text-xs text-indigo-400 hover:underline">? Back to My Timesheets</Link>
        <h1 className="text-2xl font-bold text-white tracking-tight">Team Timesheets & Approvals</h1>
        <p className="text-sm text-slate-400">Review and approve team member submitted hours from {from} to {to}</p>
      </div>

      <Card title="Pending Approvals">
        <Table columns={pendingColumns} data={pendingTimesheets} emptyMessage="No timesheets waiting for approval." />
      </Card>

      <Card title="Team Member Totals">
        <div className="space-y-2 pt-2">
          {summary.map((row: any) => (
            <div key={row.employee_id} className="flex items-center justify-between p-3 rounded bg-slate-900 border border-slate-800">
              <span className="text-sm font-semibold text-white">{row.employee_name}</span>
              <div className="flex gap-4 text-xs">
                <span>Total: <strong className="text-white">{row.total_hours}h</strong></span>
                <span>Approved: <strong className="text-emerald-400">{row.approved_hours}h</strong></span>
                <span>Pending: <strong className="text-amber-400">{row.pending_hours}h</strong></span>
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
