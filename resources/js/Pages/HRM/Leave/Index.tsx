import React, { useState } from 'react';
import { Head, usePage, useForm, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Button } from '@/Components/UI/Button';
import { Modal } from '@/Components/UI/Modal';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { DataTable, Column, LaravelPaginator } from '@/Components/UI/DataTable';
import { Badge } from '@/Components/UI/Badge';
import { formatDate } from '@/lib/format';
import { Plus, Calendar, Check, X } from 'lucide-react';

interface LeaveRequest {
  id: number;
  employee?: { name: string };
  type?: { name: string };
  starts_on: string;
  ends_on: string;
  status: string;
}

export default function LeaveIndex() {
  const { leaves = { data: [] }, leaveTypes = [], isManager = false, flash = {} } = usePage<any>().props;
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, reset, errors } = useForm({
    leave_type_id: '',
    starts_on: '',
    ends_on: '',
    reason: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/hrm/leave/request', {
      onSuccess: () => {
        reset();
        setShowModal(false);
      },
    });
  };

  const handleReview = (id: number, status: string) => {
    router.post(`/hrm/leave/${id}/review`, { status });
  };

  const columns: Column<LeaveRequest>[] = [
    {
      header: 'Employee',
      render: (row) => (
        <span className="font-medium text-[var(--text-primary)]">
          {row.employee?.name || '—'}
        </span>
      ),
    },
    {
      header: 'Leave Type',
      render: (row) => (
        <span className="text-xs text-[var(--text-secondary)]">
          {row.type?.name || 'Standard'}
        </span>
      ),
    },
    {
      header: 'Start Date',
      accessorKey: 'starts_on',
      render: (row) => (
        <span className="text-xs tabular-nums text-[var(--text-tertiary)]">
          {formatDate(row.starts_on)}
        </span>
      ),
    },
    {
      header: 'End Date',
      accessorKey: 'ends_on',
      render: (row) => (
        <span className="text-xs tabular-nums text-[var(--text-tertiary)]">
          {formatDate(row.ends_on)}
        </span>
      ),
    },
    {
      header: 'Status',
      accessorKey: 'status',
      render: (row) => (
        <Badge
          variant={
            row.status === 'approved'
              ? 'success'
              : row.status === 'rejected'
              ? 'danger'
              : 'neutral'
          }
        >
          {row.status}
        </Badge>
      ),
    },
    {
      header: 'Actions',
      render: (row) =>
        isManager && row.status === 'pending' ? (
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              icon={<Check className="w-3.5 h-3.5 text-emerald-400" />}
              onClick={() => handleReview(row.id, 'approved')}
            >
              Approve
            </Button>
            <Button
              variant="danger"
              size="sm"
              icon={<X className="w-3.5 h-3.5" />}
              onClick={() => handleReview(row.id, 'rejected')}
            >
              Reject
            </Button>
          </div>
        ) : (
          <span className="text-xs text-[var(--text-tertiary)] capitalize">{row.status}</span>
        ),
    },
  ];

  return (
    <AppShell title="Leave Management">
      <Head title="HRM — Leave Requests" />
      <div className="space-y-6">
        {flash?.success && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {flash.success}
          </div>
        )}

        <SectionHeader
          title="Leave Management"
          description="Submit absence requests and review approvals for your workforce."
          actions={
            <Button
              variant="neutral"
              size="sm"
              icon={<Plus className="w-4 h-4" />}
              onClick={() => setShowModal(true)}
            >
              Request Leave
            </Button>
          }
        />

        <DataTable
          data={leaves}
          columns={columns}
          keyExtractor={(row) => row.id}
          searchPlaceholder="Search leave records..."
          emptyTitle="No leave requests logged"
          emptyDescription="Employee leave requests and time-off tracking will appear here."
        />

        <Modal
          isOpen={showModal}
          onClose={() => setShowModal(false)}
          title="Submit Leave Request"
          description="Select leave category and requested dates."
        >
          <form onSubmit={handleSubmit} className="space-y-4 pt-2">
            <Select
              label="Leave Type"
              value={data.leave_type_id}
              onChange={(e) => setData('leave_type_id', e.target.value)}
              options={[
                { value: '', label: 'Select leave type' },
                ...leaveTypes.map((t: any) => ({
                  value: t.id,
                  label: `${t.name} (${t.days || t.annual_allowance || 0} days)`,
                })),
              ]}
              error={errors.leave_type_id}
              required
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                type="date"
                label="Start Date"
                value={data.starts_on}
                onChange={(e) => setData('starts_on', e.target.value)}
                error={errors.starts_on}
                required
              />
              <Input
                type="date"
                label="End Date"
                value={data.ends_on}
                onChange={(e) => setData('ends_on', e.target.value)}
                error={errors.ends_on}
                required
              />
            </div>
            <Input
              type="text"
              label="Reason (Optional)"
              value={data.reason}
              onChange={(e) => setData('reason', e.target.value)}
              error={errors.reason}
              placeholder="Provide context for manager approval..."
            />
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="ghost" size="sm" onClick={() => setShowModal(false)}>
                Cancel
              </Button>
              <Button type="submit" variant="neutral" size="sm" loading={processing}>
                Submit Request
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    </AppShell>
  );
}
