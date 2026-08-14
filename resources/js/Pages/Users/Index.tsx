import React, { useState } from 'react';
import { usePage, useForm, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { Modal } from '@/Components/UI/Modal';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { 
  Users, 
  Plus, 
  KeyRound, 
  UserCheck, 
  CreditCard, 
  CheckCircle, 
  XCircle, 
  Trash2,
  ShieldCheck,
  Activity
} from 'lucide-react';

export default function UsersIndex() {
  const { users = [], plans = [], roles = [], isSuperAdmin } = usePage<any>().props;

  // Password Reset Modal State
  const [passwordModalUser, setPasswordModalUser] = useState<any | null>(null);
  const [newPassword, setNewPassword] = useState('');

  // Plan Assignment Modal State
  const [planModalUser, setPlanModalUser] = useState<any | null>(null);
  const [selectedPlanId, setSelectedPlanId] = useState<number | string>('');

  const handleToggleStatus = (userId: number) => {
    router.post(`/users/${userId}/toggle-status`);
  };

  const handleImpersonate = (userId: number) => {
    if (confirm('Impersonate this tenant user session?')) {
      router.post(`/users/${userId}/impersonate`);
    }
  };

  const handlePasswordReset = (e: React.FormEvent) => {
    e.preventDefault();
    if (!passwordModalUser || !newPassword) return;

    router.post(`/users/${passwordModalUser.id}/change-password`, {
      password: newPassword,
      password_confirmation: newPassword,
    }, {
      onSuccess: () => {
        setPasswordModalUser(null);
        setNewPassword('');
      },
    });
  };

  const handleAssignPlan = (e: React.FormEvent) => {
    e.preventDefault();
    if (!planModalUser || !selectedPlanId) return;

    router.post(`/users/${planModalUser.id}/assign-plan`, {
      plan_id: selectedPlanId,
    }, {
      onSuccess: () => {
        setPlanModalUser(null);
        setSelectedPlanId('');
      },
    });
  };

  const columns: Column<any>[] = [
    {
      key: 'name',
      header: 'Member / Name',
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-violet-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-inner">
            {row.name?.charAt(0) || 'U'}
          </div>
          <div>
            <span className="font-semibold text-white block">{row.name}</span>
            <span className="text-[11px] text-gray-400">{row.email}</span>
          </div>
        </div>
      ),
    },
    {
      key: 'role',
      header: 'Role',
      sortable: true,
      render: (row) => (
        <Badge variant={row.type === 'super admin' || row.is_super_admin ? 'purple' : 'neutral'} size="sm">
          {row.type || (row.roles && row.roles[0]?.name) || 'Member'}
        </Badge>
      ),
    },
    {
      key: 'plan',
      header: 'Active Plan',
      sortable: true,
      render: (row) => (
        <span className="text-xs font-semibold text-purple-300">
          {row.plan?.name || (row.active_plan ? `Plan #${row.active_plan}` : 'Default')}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'Status',
      sortable: true,
      render: (row) => (
        <button
          onClick={() => handleToggleStatus(row.id)}
          className="cursor-pointer group inline-flex"
          title="Click to toggle active status"
        >
          <StatusBadge status={row.is_active ?? true} />
        </button>
      ),
    },
    {
      key: 'actions',
      header: 'Operations',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5 flex-wrap">
          {isSuperAdmin && (
            <Button
              variant="intelligence"
              size="sm"
              icon={<UserCheck className="w-3.5 h-3.5" />}
              onClick={() => handleImpersonate(row.id)}
            >
              Impersonate
            </Button>
          )}

          {isSuperAdmin && (
            <Button
              variant="ghost"
              size="sm"
              icon={<CreditCard className="w-3.5 h-3.5 text-purple-400" />}
              onClick={() => {
                setPlanModalUser(row);
                setSelectedPlanId(row.active_plan || (plans[0]?.id ?? ''));
              }}
            >
              Plan
            </Button>
          )}

          <Button
            variant="ghost"
            size="sm"
            icon={<KeyRound className="w-3.5 h-3.5 text-amber-400" />}
            onClick={() => setPasswordModalUser(row)}
          >
            Password
          </Button>
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Users & Team Members">
      <div className="space-y-6">
        <SectionHeader
          title="Team & User Administration"
          description="Manage workspace memberships, RBAC assignments, plan quotas, password resets, and session impersonation."
          badge={<Badge variant="purple" size="sm">User Directory</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/users-login-history">
                <Button variant="secondary" size="sm" icon={<Activity className="w-4 h-4" />}>
                  Login History
                </Button>
              </Link>
              <Link href="/users/create">
                <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                  Add Member
                </Button>
              </Link>
            </div>
          }
        />

        <DataTable
          columns={columns}
          data={users}
          searchPlaceholder="Search by name, email or role..."
          searchKeys={['name', 'email', 'type']}
          emptyTitle="No members found"
          emptyDescription="Invite your team members to collaborate."
        />

        {/* Change Password Modal */}
        <Modal
          isOpen={Boolean(passwordModalUser)}
          onClose={() => setPasswordModalUser(null)}
          title={`Reset Password for ${passwordModalUser?.name}`}
          description="Set a new secure password for this user account."
        >
          <form onSubmit={handlePasswordReset} className="space-y-4">
            <Input
              label="New Password"
              type="password"
              placeholder="Minimum 8 characters"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              required
            />
            <div className="pt-4 flex items-center justify-end gap-2">
              <Button type="button" variant="ghost" onClick={() => setPasswordModalUser(null)}>
                Cancel
              </Button>
              <Button type="submit" variant="primary">
                Update Password
              </Button>
            </div>
          </form>
        </Modal>

        {/* Assign Plan Modal */}
        <Modal
          isOpen={Boolean(planModalUser)}
          onClose={() => setPlanModalUser(null)}
          title={`Assign Plan to ${planModalUser?.name}`}
          description="Upgrade or assign an enterprise subscription tier manually."
        >
          <form onSubmit={handleAssignPlan} className="space-y-4">
            <Select
              label="Select Subscription Plan"
              value={selectedPlanId}
              onChange={(e) => setSelectedPlanId(e.target.value)}
              required
            >
              {plans.map((p: any) => (
                <option key={p.id} value={p.id}>
                  {p.name} (${p.package_price_monthly}/mo) — {p.number_of_users} Users
                </option>
              ))}
            </Select>
            <div className="pt-4 flex items-center justify-end gap-2">
              <Button type="button" variant="ghost" onClick={() => setPlanModalUser(null)}>
                Cancel
              </Button>
              <Button type="submit" variant="intelligence">
                Assign Plan
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    </AppShell>
  );
}
