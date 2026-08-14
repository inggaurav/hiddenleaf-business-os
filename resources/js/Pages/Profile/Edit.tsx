import React from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { User, Lock, Save, ShieldCheck } from 'lucide-react';

export default function ProfileEdit() {
  const { auth } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    name: auth?.user?.name || '',
    email: auth?.user?.email || '',
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const handleUpdateProfile = (e: React.FormEvent) => {
    e.preventDefault();
    post('/profile');
  };

  return (
    <AppShell title="Account Profile">
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title="Account Profile & Security"
          description="Manage your administrator credentials, email notifications, and active session authentication."
          badge={<Badge variant="purple" size="sm">Active Identity</Badge>}
        />

        <form onSubmit={handleUpdateProfile} className="space-y-6">
          <Card level={0} className="space-y-4">
            <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
              Profile Information
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
            </div>
          </Card>

          <Card level={0} className="space-y-4">
            <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
              Change Security Password
            </h3>
            <Input
              label="Current Password"
              type="password"
              value={data.current_password}
              onChange={(e) => setData('current_password', e.target.value)}
              error={errors.current_password}
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="New Password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
              />
              <Input
                label="Confirm New Password"
                type="password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
              />
            </div>

            <div className="pt-4 flex items-center justify-end">
              <Button
                type="submit"
                variant="primary"
                size="md"
                loading={processing}
                icon={<Save className="w-4 h-4" />}
              >
                Update Profile
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
