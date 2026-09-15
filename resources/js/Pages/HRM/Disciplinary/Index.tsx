import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function DisciplinaryIndex() {
  const { cases = { data: [] }, employees = [], flash = {} } = usePage<any>().props;
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    employee_id: '',
    type: 'Misconduct',
    severity: 'minor',
    incident_date: new Date().toISOString().split('T')[0],
    incident_description: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/disciplinary', formData);
  };

  const columns = [
    { header: 'Case #', accessor: 'case_number' as const },
    {
      header: 'Employee',
      accessor: 'employee' as const,
      render: (emp: any) => emp?.name || 'N/A',
    },
    { header: 'Type', accessor: 'type' as const },
    { header: 'Severity', accessor: 'severity' as const },
    { header: 'Incident Date', accessor: 'incident_date' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any) => (
        <Link href={`/hrm/disciplinary/${id}`}>
          <Button variant="ghost" size="sm">Manage Case</Button>
        </Link>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Disciplinary" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Disciplinary & Grievances</h1>
          <p className="text-sm text-slate-400">Formal grievance tracking, investigations, and corrective actions.</p>
        </div>
        <Button variant="danger" size="sm" onClick={() => setShowModal(!showModal)}>
          Open Case
        </Button>
      </div>

      {showModal && (
        <Card title="Open Disciplinary Case" subtitle="Log workplace incident">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                placeholder="Case Type"
                className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.type}
                onChange={e => setFormData({ ...formData, type: e.target.value })}
                required
              />
              <select
                className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.severity}
                onChange={e => setFormData({ ...formData, severity: e.target.value })}
              >
                <option value="minor">Minor</option>
                <option value="moderate">Moderate</option>
                <option value="major">Major</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <textarea
              placeholder="Incident description and evidence notes"
              className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              rows={3}
              value={formData.incident_description}
              onChange={e => setFormData({ ...formData, incident_description: e.target.value })}
              required
            />
            <Button type="submit" variant="danger">Submit Formal Case</Button>
          </form>
        </Card>
      )}

      <Card title="Recorded Disciplinary Cases">
        <Table columns={columns} data={cases.data || []} emptyMessage="No disciplinary cases on record." />
      </Card>
    </div>
  );
}
