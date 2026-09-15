import React, { useState } from 'react';
import { Head, usePage, useForm, router, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Button } from '@/Components/UI/Button';
import { Modal } from '@/Components/UI/Modal';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { DataTable, Column, LaravelPaginator } from '@/Components/UI/DataTable';
import { Badge } from '@/Components/UI/Badge';
import { formatDate } from '@/lib/format';
import { UserMinus, CheckCircle2, ClipboardCheck } from 'lucide-react';

interface ExitRecord {
  id: number;
  name: string;
  exit_date: string;
  exit_reason: string;
  status: string;
  exit_interview?: boolean;
}

export default function ExitIndex() {
  const { exits = { data: [] }, employees = [], flash = {} } = usePage<any>().props;
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, reset, errors } = useForm({
    employee_id: '',
    exit_reason: 'Resignation',
    exit_date: new Date().toISOString().split('T')[0],
  });

  const handleInitiate = (e: React.FormEvent) => {
    e.preventDefault();
    post('/hrm/exit/initiate', {
      onSuccess: () => {
        reset();
        setShowModal(false);
      },
    });
  };

  const handleInterview = (empId: number) => {
    const reason = prompt('Reason for exit:');
    if (reason) {
      router.post(`/hrm/exit/${empId}/interview`, { exit_reason: reason });
    }
  };

  const handleComplete = (empId: number) => {
    if (confirm('Complete formal exit and terminate employee status?')) {
      router.post(`/hrm/exit/${empId}/complete`);
    }
  };

  const columns: Column<ExitRecord>[] = [
    {
      header: 'Employee',
      accessorKey: 'name',
      render: (row) => (
        <span className="font-medium text-[var(--text-primary)]">
          {row.name}
        </span>
      ),
    },
    {
      header: 'Exit Date',
      accessorKey: 'exit_date',
      render: (row) => (
        <span className="text-xs tabular-nums text-[var(--text-tertiary)]">
          {formatDate(row.exit_date)}
        </span>
      ),
    },
    {
      header: 'Reason',
      accessorKey: 'exit_reason',
      render: (row) => (
        <span className="text-xs text-[var(--text-secondary)]">
          {row.exit_reason || 'Resignation'}
        </span>
      ),
    },
    {
      header: 'Status',
      accessorKey: 'status',
      render: (row) => (
        <Badge
          variant={
            row.status === 'completed' || row.status === 'terminated'
              ? 'neutral'
              : 'warning'
          }
        >
          {row.status}
        </Badge>
      ),
    },
    {
      header: 'Actions',
      render: (row) => (
        <div className="flex items-center gap-2">
          {!row.exit_interview && (
            <Button variant="outline" size="sm" onClick={() => handleInterview(row.id)}>
              Interview
            </Button>
          )}
          <Link href={`/hrm/exit/${row.id}/clearance`}>
            <Button variant="ghost" size="sm" icon={<ClipboardCheck className="w-3.5 h-3.5" />}>
              Clearance
            </Button>
          </Link>
          {row.status !== 'terminated' && (
            <Button variant="danger" size="sm" onClick={() => handleComplete(row.id)}>
              Complete Exit
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Exit Management">
      <Head title="HRM — Exit Management" />
      <div className="space-y-6">
        {flash?.success && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {flash.success}
          </div>
        )}

        <SectionHeader
          title="Exit & Offboarding"
          description="Exit interviews, departmental clearances, and formal separation completion."
          actions={
            <Button
              variant="neutral"
              size="sm"
              icon={<UserMinus className="w-4 h-4" />}
              onClick={() => setShowModal(true)}
            >
              Initiate Exit
            </Button>
          }
        />

        <DataTable
          data={exits}
          columns={columns}
          keyExtractor={(row) => row.id}
          searchPlaceholder="Search exit records..."
          emptyTitle="No employee exits recorded"
          emptyDescription="Employee offboarding and separation workflows will appear here."
        />

        <Modal
          isOpen={showModal}
          onClose={() => setShowModal(false)}
          title="Initiate Employee Separation"
          description="Schedule the exit date and start the clearance workflow."
        >
          <form onSubmit={handleInitiate} className="space-y-4 pt-2">
            <Select
              label="Employee"
              value={data.employee_id}
              onChange={(e) => setData('employee_id', e.target.value)}
              options={[
                { value: '', label: 'Select employee' },
                ...employees.map((e: any) => ({ value: e.id, label: e.name })),
              ]}
              error={errors.employee_id}
              required
            />
            <Input
              type="text"
              label="Exit Reason"
              value={data.exit_reason}
              onChange={(e) => setData('exit_reason', e.target.value)}
              error={errors.exit_reason}
              placeholder="Resignation, retirement, relocation..."
              required
            />
            <Input
              type="date"
              label="Exit Date"
              value={data.exit_date}
              onChange={(e) => setData('exit_date', e.target.value)}
              error={errors.exit_date}
              required
            />
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="ghost" size="sm" onClick={() => setShowModal(false)}>
                Cancel
              </Button>
              <Button type="submit" variant="neutral" size="sm" loading={processing}>
                Start Exit Process
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    </AppShell>
  );
}
