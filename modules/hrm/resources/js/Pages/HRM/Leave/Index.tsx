import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function LeaveIndex() {
  const { leaves = { data: [] }, leaveTypes = [], isManager = false, flash = {} } = usePage<any>().props;
  const [showRequest, setShowRequest] = useState(false);
  const [formData, setFormData] = useState({
    leave_type_id: '',
    starts_on: '',
    ends_on: '',
    reason: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/leave/request', formData, {
      onSuccess: () => setShowRequest(false),
    });
  };

  const handleReview = (id: number, status: string) => {
    router.post(`/hrm/leave/${id}/review`, { status });
  };

  const columns = [
    {
      header: 'Employee',
      accessor: 'employee' as const,
      render: (emp: any) => emp?.name || 'N/A',
    },
    {
      header: 'Leave Type',
      accessor: 'type' as const,
      render: (t: any) => t?.name || 'Standard',
    },
    { header: 'Starts', accessor: 'starts_on' as const },
    { header: 'Ends', accessor: 'ends_on' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any, row: any) => isManager && row.status === 'pending' ? (
        <div className="flex gap-2">
          <Button variant="primary" size="sm" onClick={() => handleReview(id, 'approved')}>Approve</Button>
          <Button variant="danger" size="sm" onClick={() => handleReview(id, 'rejected')}>Reject</Button>
        </div>
      ) : (
        <span className="text-xs text-slate-400 capitalize">{row.status}</span>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Leave" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Leave Management</h1>
          <p className="text-sm text-slate-400">Submit absence requests and review approvals.</p>
        </div>
        <Button variant="primary" size="sm" onClick={() => setShowRequest(!showRequest)}>
          Request Leave
        </Button>
      </div>

      {showRequest && (
        <Card title="Submit Leave Request" subtitle="Choose leave category and date range">
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <select
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.leave_type_id}
              onChange={e => setFormData({ ...formData, leave_type_id: e.target.value })}
              required
            >
              <option value="">Select Leave Type</option>
              {leaveTypes.map((t: any) => <option key={t.id} value={t.id}>{t.name} ({t.days} days)</option>)}
            </select>
            <input
              type="date"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.starts_on}
              onChange={e => setFormData({ ...formData, starts_on: e.target.value })}
              required
            />
            <input
              type="date"
              className="bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={formData.ends_on}
              onChange={e => setFormData({ ...formData, ends_on: e.target.value })}
              required
            />
            <Button type="submit" variant="primary">Submit Request</Button>
          </form>
        </Card>
      )}

      <Card title="Leave Requests">
        <Table columns={columns} data={leaves.data || []} emptyMessage="No leave requests logged." />
      </Card>
    </div>
  );
}
