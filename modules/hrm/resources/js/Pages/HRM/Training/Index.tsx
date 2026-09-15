import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function TrainingIndex() {
  const { programs = { data: [] }, flash = {} } = usePage<any>().props;
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    title: '',
    category: 'technical',
    trainer_name: '',
    duration_hours: '',
    starts_on: '',
    ends_on: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/training', formData, {
      onSuccess: () => setShowModal(false),
    });
  };

  const columns = [
    { header: 'Title', accessor: 'title' as const },
    { header: 'Category', accessor: 'category' as const },
    { header: 'Trainer', accessor: 'trainer_name' as const },
    { header: 'Starts', accessor: 'starts_on' as const },
    { header: 'Ends', accessor: 'ends_on' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any) => (
        <Link href={`/hrm/training/${id}`}>
          <Button variant="ghost" size="sm">Manage</Button>
        </Link>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Training" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Training Programs</h1>
          <p className="text-sm text-slate-400">Professional development, technical upskilling, and certifications.</p>
        </div>
        <Button variant="primary" size="sm" onClick={() => setShowModal(!showModal)}>
          New Program
        </Button>
      </div>

      {showModal && (
        <Card title="Create Training Program" subtitle="Define workshop or course">
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input
              placeholder="Program Title"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.title}
              onChange={e => setFormData({ ...formData, title: e.target.value })}
              required
            />
            <input
              placeholder="Trainer Name"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.trainer_name}
              onChange={e => setFormData({ ...formData, trainer_name: e.target.value })}
            />
            <input
              type="number"
              placeholder="Duration (Hours)"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.duration_hours}
              onChange={e => setFormData({ ...formData, duration_hours: e.target.value })}
            />
            <Button type="submit" variant="primary">Create</Button>
          </form>
        </Card>
      )}

      <Card title="Programs">
        <Table columns={columns} data={programs.data || []} emptyMessage="No training programs planned." />
      </Card>
    </div>
  );
}
