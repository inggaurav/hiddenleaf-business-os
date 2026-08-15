import React, { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { Modal } from '@/Components/UI/Modal';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Activity, CreditCard, KeyRound, Plus, UserCheck } from 'lucide-react';

export default function UsersIndex() {
  const { users, plans = [], isSuperAdmin = false } = usePage<any>().props;
  const rows = users?.data || users || [];
  const [passwordModalUser, setPasswordModalUser] = useState<any | null>(null);
  const [newPassword, setNewPassword] = useState('');
  const [planModalUser, setPlanModalUser] = useState<any | null>(null);
  const [selectedPlanId, setSelectedPlanId] = useState<number | string>('');

  const handlePasswordReset = (event: React.FormEvent) => {
    event.preventDefault();
    if (!passwordModalUser || !newPassword) return;
    router.post(`/users/${passwordModalUser.id}/change-password`, {
      password: newPassword,
      password_confirmation: newPassword,
    }, { onSuccess: () => { setPasswordModalUser(null); setNewPassword(''); } });
  };

  const handleAssignPlan = (event: React.FormEvent) => {
    event.preventDefault();
    if (!planModalUser || !selectedPlanId) return;
    router.post(`/users/${planModalUser.id}/assign-plan`, { plan_id: selectedPlanId }, {
      onSuccess: () => { setPlanModalUser(null); setSelectedPlanId(''); },
    });
  };

  const columns: Column<any>[] = [
    {
      key: 'name',
      header: 'Member',
      sortable: true,
      render: (row) => <div className="flex items-center gap-2.5"><div className="w-8 h-8 rounded-full bg-gradient-to-tr from-violet-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center">{row.name?.charAt(0) || 'U'}</div><div><span className="font-semibold text-white block">{row.name}</span><span className="text-[11px] text-gray-400">{row.email}</span></div></div>,
    },
    {
      key: 'workspace_role',
      header: 'Workspace Role',
      sortable: true,
      render: (row) => <Badge variant={row.is_super_admin || row.role === 'super_admin' ? 'purple' : 'neutral'} size="sm">{row.is_super_admin || row.role === 'super_admin' ? 'Super Admin' : (row.workspace_role || row.role || 'Member')}</Badge>,
    },
    {
      key: 'is_active',
      header: 'Status',
      sortable: true,
      render: (row) => <button onClick={() => router.post(`/users/${row.id}/toggle-status`)} className="cursor-pointer inline-flex" title="Toggle active status"><StatusBadge status={row.is_active ?? true} /></button>,
    },
    {
      key: 'actions',
      header: 'Operations',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5 flex-wrap">
          <Link href={`/users/${row.id}/edit`}><Button variant="ghost" size="sm">Edit</Button></Link>
          {isSuperAdmin && <Button variant="intelligence" size="sm" icon={<UserCheck className="w-3.5 h-3.5" />} onClick={() => confirm('Impersonate this tenant user session?') && router.post(`/users/${row.id}/impersonate`)}>Impersonate</Button>}
          {isSuperAdmin && <Button variant="ghost" size="sm" icon={<CreditCard className="w-3.5 h-3.5 text-purple-400" />} onClick={() => { setPlanModalUser(row); setSelectedPlanId(plans[0]?.id ?? ''); }}>Plan</Button>}
          <Button variant="ghost" size="sm" icon={<KeyRound className="w-3.5 h-3.5 text-amber-400" />} onClick={() => setPasswordModalUser(row)}>Password</Button>
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Users & Team Members" breadcrumbs={[{ label: 'Team & Access' }, { label: 'Users' }]}>
      <div className="space-y-6">
        <SectionHeader
          title="Team & User Administration"
          description="Manage workspace memberships, RBAC roles, password resets and account state."
          badge={<Badge variant="purple" size="sm">{users?.total ?? rows.length} Members</Badge>}
          actions={<div className="flex items-center gap-2"><Link href="/users-login-history"><Button variant="secondary" size="sm" icon={<Activity className="w-4 h-4" />}>Login History</Button></Link><Link href="/users/create"><Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>Add Member</Button></Link></div>}
        />
        <DataTable columns={columns} data={rows} searchPlaceholder="Search by name, email or role..." searchKeys={['name', 'email', 'workspace_role']} emptyTitle="No members found" emptyDescription="Add team members and assign workspace roles." />

        <Modal isOpen={Boolean(passwordModalUser)} onClose={() => setPasswordModalUser(null)} title={`Reset Password for ${passwordModalUser?.name}`} description="Set a new secure password for this user account.">
          <form onSubmit={handlePasswordReset} className="space-y-4"><Input label="New Password" type="password" placeholder="Minimum 8 characters" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} required /><div className="pt-4 flex justify-end gap-2"><Button type="button" variant="ghost" onClick={() => setPasswordModalUser(null)}>Cancel</Button><Button type="submit" variant="primary">Update Password</Button></div></form>
        </Modal>

        <Modal isOpen={Boolean(planModalUser)} onClose={() => setPlanModalUser(null)} title={`Assign Legacy User Plan to ${planModalUser?.name}`} description="Organization plans should normally be managed from Super Admin → Companies.">
          <form onSubmit={handleAssignPlan} className="space-y-4"><Select label="Select Plan" value={selectedPlanId} onChange={(e) => setSelectedPlanId(e.target.value)} required>{plans.map((plan: any) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}</Select><div className="pt-4 flex justify-end gap-2"><Button type="button" variant="ghost" onClick={() => setPlanModalUser(null)}>Cancel</Button><Button type="submit" variant="intelligence">Assign Plan</Button></div></form>
        </Modal>
      </div>
    </AppShell>
  );
}
