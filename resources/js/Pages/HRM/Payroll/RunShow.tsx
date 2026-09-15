import React from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card, MetricCard } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function PayrollRunShow() {
  const { run = {}, payslips = [], flash = {} } = usePage<any>().props;

  const handleAddAll = () => {
    router.post(`/hrm/payroll/runs/${run.id}/add-all`);
  };

  const handleApprove = () => {
    router.post(`/hrm/payroll/runs/${run.id}/approve`);
  };

  const handlePay = () => {
    if (confirm('Disburse this approved payroll run?')) {
      router.post(`/hrm/payroll/runs/${run.id}/pay`);
    }
  };

  const columns = [
    {
      header: 'Employee',
      accessor: 'employee_id' as const,
      render: (id: any, row: any) => `Employee #${id}`,
    },
    { header: 'Gross Pay', accessor: 'gross_pay' as const },
    { header: 'Deductions', accessor: 'deductions' as const },
    { header: 'Net Pay', accessor: 'net_pay' as const },
    { header: 'Status', accessor: 'status' as const },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title={`HRM ? Payroll Run: ${run.run_number}`} />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <Link href="/hrm/payroll" className="text-xs text-indigo-400 hover:underline">? Back to Payroll</Link>
          <h1 className="text-2xl font-bold text-white tracking-tight">Payroll Run: {run.run_number}</h1>
          <p className="text-sm text-slate-400">Period: {run.period_start} to {run.period_end} ? Status: <span className="font-semibold uppercase text-indigo-400">{run.status}</span></p>
        </div>
        <div className="flex gap-2">
          {run.status === 'draft' && (
            <>
              <Button variant="outline" size="sm" onClick={handleAddAll}>Include All Active</Button>
              <Button variant="primary" size="sm" onClick={handleApprove}>Approve Run</Button>
            </>
          )}
          {run.status === 'approved' && (
            <Button variant="primary" size="sm" onClick={handlePay}>Disburse Payment</Button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <MetricCard label="Employees" value={run.employee_count ?? 0} />
        <MetricCard label="Total Gross" value={run.total_gross ?? 0} />
        <MetricCard label="Total Deductions" value={run.total_deductions ?? 0} />
        <MetricCard label="Total Net Payable" value={run.total_net ?? 0} />
      </div>

      <Card title="Generated Payslips">
        <Table columns={columns} data={payslips} emptyMessage="No payslips added to this run yet." />
      </Card>
    </div>
  );
}
