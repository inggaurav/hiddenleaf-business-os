import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function OnboardingIndex() {
  const { onboardings = { data: [] }, templates = [], employees = [], flash = {} } = usePage<any>().props;
  const [showStart, setShowStart] = useState(false);
  const [showTemplate, setShowTemplate] = useState(false);

  const [startData, setStartData] = useState({ employee_id: '', template_id: '' });
  const [templateData, setTemplateData] = useState({ title: '', description: '', is_default: false });

  const handleStart = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/onboarding/start', startData);
  };

  const handleCreateTemplate = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/onboarding/templates', templateData, {
      onSuccess: () => setShowTemplate(false),
    });
  };

  const columns = [
    {
      header: 'Employee',
      accessor: 'employee' as const,
      render: (emp: any) => emp?.name || 'N/A',
    },
    {
      header: 'Template',
      accessor: 'template' as const,
      render: (tmpl: any) => tmpl?.title || 'Standard',
    },
    { header: 'Started On', accessor: 'started_on' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any) => (
        <Link href={`/hrm/onboarding/${id}`}>
          <Button variant="ghost" size="sm">View Checklist</Button>
        </Link>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Onboarding" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Employee Onboarding</h1>
          <p className="text-sm text-slate-400">Manage onboarding workflows, task checklists, and buddy allocations.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" size="sm" onClick={() => setShowTemplate(!showTemplate)}>
            New Template
          </Button>
          <Button variant="primary" size="sm" onClick={() => setShowStart(!showStart)}>
            Start Onboarding
          </Button>
        </div>
      </div>

      {showStart && (
        <Card title="Initiate Employee Onboarding" subtitle="Assign template checklist">
          <form onSubmit={handleStart} className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <select
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={startData.employee_id}
              onChange={e => setStartData({ ...startData, employee_id: e.target.value })}
              required
            >
              <option value="">Select Employee</option>
              {employees.map((e: any) => <option key={e.id} value={e.id}>{e.name}</option>)}
            </select>
            <select
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={startData.template_id}
              onChange={e => setStartData({ ...startData, template_id: e.target.value })}
            >
              <option value="">Default Template</option>
              {templates.map((t: any) => <option key={t.id} value={t.id}>{t.title}</option>)}
            </select>
            <Button type="submit" variant="primary">Launch Onboarding</Button>
          </form>
        </Card>
      )}

      {showTemplate && (
        <Card title="Create Onboarding Template" subtitle="Define standard onboarding workflow">
          <form onSubmit={handleCreateTemplate} className="space-y-4">
            <input
              placeholder="Template Title (e.g. Engineering Onboarding)"
              className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={templateData.title}
              onChange={e => setTemplateData({ ...templateData, title: e.target.value })}
              required
            />
            <Button type="submit" variant="primary">Save Template</Button>
          </form>
        </Card>
      )}

      <Card title="Active Onboardings">
        <Table columns={columns} data={onboardings.data || []} emptyMessage="No onboarding runs active." />
      </Card>
    </div>
  );
}
