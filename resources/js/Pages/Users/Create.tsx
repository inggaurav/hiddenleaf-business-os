import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, UserPlus } from 'lucide-react';

export default function UserCreate() {
  const { roles = [], plans = [], isSuperAdmin } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: roles[0]?.id || '',
    plan_id: plans[0]?.id || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/users');
  };

  return (
    <AppShell title="Add Team Member">
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title="Add Team Member"
          description="Provision credentials and assign an organizational RBAC role."
          actions={
            <Link href="/users">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Users
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <Input
              label="Full Name"
              placeholder="e.g. Elena Rostova"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              error={errors.name}
              required
            />
            <Input
              label="Email Address"
              type="email"
              placeholder="e.g. elena@company.com"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              error={errors.email}
              required
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
                required
              />
              <Input
                label="Confirm Password"
                type="password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                required
              />
            </div>

            {roles.length > 0 && (
              <Select
                label="Assign Role"
                value={data.role_id}
                onChange={(e) => setData('role_id', e.target.value)}
              >
                {roles.map((r: any) => (
                  <option key={r.id} value={r.id}>
                    {r.display_name || r.name}
                  </option>
                ))}
              </Select>
            )}

            {isSuperAdmin && plans.length > 0 && (
              <Select
                label="Initial Plan Allocation (Optional)"
                value={data.plan_id}
                onChange={(e) => setData('plan_id', e.target.value)}
              >
                <option value="">Default Plan</option>
                {plans.map((p: any) => (
                  <option key={p.id} value={p.id}>
                    {p.name} (${p.package_price_monthly}/mo)
                  </option>
                ))}
              </Select>
            )}

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/users">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<UserPlus className="w-4 h-4" />}>
                Create User
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
