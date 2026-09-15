import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function TimesheetsIndex() {
  const { employee = null, weekStart = '', weekly = { days: {}, total_hours: 0 }, recentTimesheets = [], flash = {} } = usePage<any>().props;
  const [formData, setFormData] = useState({ work_date: new Date().toISOString().split('T')[0], hours: '8', project_code: '', description: '' });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/timesheets', formData);
  };

  const columns = [
    { header: 'Date', accessor: 'work_date' as const },
    { header: 'Project Code', accessor: 'project_code' as const },
    { header: 'Hours', accessor: 'hours' as const },
    { header: 'Description', accessor: 'description' as const },
    { header: 'Status', accessor: 'status' as const },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Timesheets" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Timesheet Logging</h1>
          <p className="text-sm text-slate-400">Employee: {employee?.name || 'Self'} ? Week of {weekStart}</p>
        </div>
        <Link href="/hrm/timesheets/team">
          <Button variant="secondary" size="sm">Team Timesheets</Button>
        </Link>
      </div>

      <Card title="Log Work Hours" subtitle="Submit timesheet entry">
        <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <input
            type="date"
            className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
            value={formData.work_date}
            onChange={e => setFormData({ ...formData, work_date: e.target.value })}
            required
          />
          <input
            type="number"
            step="0.25"
            placeholder="Hours"
            className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
            value={formData.hours}
            onChange={e => setFormData({ ...formData, hours: e.target.value })}
            required
          />
          <input
            placeholder="Project Code"
            className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
            value={formData.project_code}
            onChange={e => setFormData({ ...formData, project_code: e.target.value })}
          />
          <Button type="submit" variant="primary">Submit Hours</Button>
        </form>
      </Card>

      <Card title="Recent Timesheets">
        <Table columns={columns} data={recentTimesheets} emptyMessage="No timesheets logged recently." />
      </Card>
    </div>
  );
}
