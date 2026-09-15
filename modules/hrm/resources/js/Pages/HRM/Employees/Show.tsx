import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';

export default function EmployeeShow() {
  const { employee = {}, documents = [], flash = {} } = usePage<any>().props;
  const [salary, setSalary] = useState(employee.basic_salary || '');
  const [terminateReason, setTerminateReason] = useState('');

  const handleSalaryUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    router.put(`/hrm/employees/${employee.id}/salary`, { basic_salary: salary });
  };

  const handleTerminate = (e: React.FormEvent) => {
    e.preventDefault();
    if (confirm('Are you sure you want to terminate this employee?')) {
      router.post(`/hrm/employees/${employee.id}/terminate`, {
        exit_reason: terminateReason,
        exit_date: new Date().toISOString().split('T')[0],
      });
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title={`HRM ? ${employee.name || 'Employee Profile'}`} />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <Link href="/hrm/employees" className="text-xs text-indigo-400 hover:underline">? Back to Employees</Link>
          <h1 className="text-2xl font-bold text-white tracking-tight">{employee.name}</h1>
          <p className="text-sm text-slate-400">{employee.employee_number} ? {employee.email} ? Status: <span className="font-semibold text-emerald-400">{employee.status}</span></p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card title="Profile Details" subtitle="Contact and employment details">
          <div className="space-y-3 pt-2 text-sm">
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Phone</span>
              <span>{employee.phone || 'N/A'}</span>
            </div>
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Employment Type</span>
              <span>{employee.employment_type || 'full_time'}</span>
            </div>
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Joined Date</span>
              <span>{employee.joined_at || 'N/A'}</span>
            </div>
            <div className="flex justify-between py-1 border-b border-slate-800">
              <span className="text-slate-400">Address</span>
              <span>{employee.address || 'N/A'}</span>
            </div>
          </div>
        </Card>

        <Card title="Compensation & Actions" subtitle="Manage salary and employment status">
          <form onSubmit={handleSalaryUpdate} className="space-y-3 mb-6">
            <label className="block text-xs text-slate-400">Basic Monthly Salary</label>
            <div className="flex gap-2">
              <input
                type="number"
                step="0.01"
                className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white flex-1"
                value={salary}
                onChange={e => setSalary(e.target.value)}
              />
              <Button type="submit" variant="secondary" size="sm">Update Salary</Button>
            </div>
          </form>

          {employee.status === 'active' && (
            <form onSubmit={handleTerminate} className="space-y-3 pt-4 border-t border-slate-800">
              <label className="block text-xs text-rose-400 font-semibold">Terminate Employment</label>
              <input
                type="text"
                placeholder="Reason for termination"
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={terminateReason}
                onChange={e => setTerminateReason(e.target.value)}
                required
              />
              <Button type="submit" variant="danger" size="sm">Confirm Termination</Button>
            </form>
          )}
        </Card>
      </div>
    </div>
  );
}
