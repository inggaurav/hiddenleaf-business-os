import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function AppraisalsIndex() {
  const { appraisals = { data: [] }, employees = [], flash = {} } = usePage<any>().props;
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    employee_id: '',
    rating: '5',
    evaluation_period: 'Q3 2026',
    remarks: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/appraisals', formData, {
      onSuccess: () => setShowModal(false),
    });
  };

  const columns = [
    {
      header: 'Employee',
      accessor: 'employee_id' as const,
      render: (id: any) => `Employee #${id}`,
    },
    { header: 'Period', accessor: 'evaluation_period' as const },
    { header: 'Rating', accessor: 'rating' as const },
    { header: 'Remarks', accessor: 'remarks' as const },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Appraisals" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Performance Appraisals</h1>
          <p className="text-sm text-slate-400">Quarterly and annual performance reviews, ratings, and feedback.</p>
        </div>
        <Button variant="primary" size="sm" onClick={() => setShowModal(!showModal)}>
          Record Appraisal
        </Button>
      </div>

      {showModal && (
        <Card title="Record Performance Evaluation" subtitle="Add employee review">
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
              placeholder="Period (e.g. Q3 2026)"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.evaluation_period}
              onChange={e => setFormData({ ...formData, evaluation_period: e.target.value })}
            />
            <input
              type="number"
              min="1"
              max="5"
              step="0.1"
              placeholder="Rating (1-5)"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.rating}
              onChange={e => setFormData({ ...formData, rating: e.target.value })}
              required
            />
            <textarea
              placeholder="Evaluation remarks and goals achieved"
              className="md:col-span-3 bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.remarks}
              onChange={e => setFormData({ ...formData, remarks: e.target.value })}
            />
            <div className="md:col-span-3 flex justify-end">
              <Button type="submit" variant="primary">Save Appraisal</Button>
            </div>
          </form>
        </Card>
      )}

      <Card title="Appraisal Records">
        <Table columns={columns} data={appraisals.data || []} emptyMessage="No performance appraisals recorded." />
      </Card>
    </div>
  );
}
