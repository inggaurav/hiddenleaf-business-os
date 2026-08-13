import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function UserEdit() {
  const { user, roles = [] } = usePage<any>().props;

  const { data, setData, put, processing, errors } = useForm({
    name: user?.name || '',
    email: user?.email || '',
    role_id: (user?.roles && user.roles[0]?.id) || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/users/${user.id}`);
  };

  return (
    <AppShell title={`Edit User: ${user?.name}`}>
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit ${user?.name}`}
          description="Update user account profile and role assignments."
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
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              error={errors.name}
              required
            />
            <Input
              label="Email Address"
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              error={errors.email}
              required
            />

            {roles.length > 0 && (
              <Select
                label="Role Assignment"
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

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/users">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Changes
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
