import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Lock, Mail, Save } from 'lucide-react';

export default function ResetPassword() {
  const { token, email } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    token: token || '',
    email: email || '',
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/reset-password');
  };

  return (
    <div className="min-h-screen bg-[var(--bg-0)] text-[var(--text-primary)] flex items-center justify-center p-4 relative overflow-hidden">
      <div className="max-w-md w-full relative z-10 space-y-6">
        <div className="text-center space-y-2">
          <h1 className="text-2xl font-bold tracking-tight text-white">Choose New Password</h1>
          <p className="text-xs text-gray-400">Set a new password for your enterprise account</p>
        </div>

        <Card level={1} className="p-6 sm:p-8 space-y-5">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label="Email Address"
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              error={errors.email}
              required
            />

            <Input
              label="New Password"
              type="password"
              leftIcon={<Lock className="w-4 h-4 text-gray-400" />}
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              error={errors.password}
              required
            />

            <Input
              label="Confirm New Password"
              type="password"
              leftIcon={<Lock className="w-4 h-4 text-gray-400" />}
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
              required
            />

            <div className="pt-2">
              <Button
                type="submit"
                variant="primary"
                size="lg"
                loading={processing}
                className="w-full"
                icon={<Save className="w-4 h-4" />}
              >
                Reset Password
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  );
}
