import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Checkbox } from '@/Components/UI/Checkbox';
import { Building, Lock, Mail, User, ArrowRight } from 'lucide-react';

export default function Register() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    organization_name: '',
    password: '',
    password_confirmation: '',
    terms: true,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/register');
  };

  return (
    <div className="min-h-screen bg-[var(--bg-0)] text-[var(--text-primary)] flex items-center justify-center p-4 relative overflow-hidden">
      <div className="max-w-md w-full relative z-10 space-y-6">
        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-xl shadow-purple-950/40 mx-auto">
            HL
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">Create Workspace</h1>
          <p className="text-xs text-gray-400">Launch your autonomous enterprise operating system</p>
        </div>

        <Card level={1} className="p-6 sm:p-8 space-y-5">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label="Company / Organization Name"
              placeholder="e.g. Acme Corporation"
              leftIcon={<Building className="w-4 h-4 text-gray-400" />}
              value={data.organization_name}
              onChange={(e) => setData('organization_name', e.target.value)}
              error={errors.organization_name}
              required
            />

            <Input
              label="Administrator Full Name"
              placeholder="e.g. Elena Rostova"
              leftIcon={<User className="w-4 h-4 text-gray-400" />}
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              error={errors.name}
              required
            />

            <Input
              label="Work Email Address"
              type="email"
              placeholder="elena@acme.com"
              leftIcon={<Mail className="w-4 h-4 text-gray-400" />}
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              error={errors.email}
              required
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Password"
                type="password"
                placeholder="••••••••"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
                required
              />
              <Input
                label="Confirm Password"
                type="password"
                placeholder="••••••••"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                required
              />
            </div>

            <div className="pt-1">
              <Checkbox
                label="I accept the Master SaaS Service Terms and Multi-Tenant Privacy Policy"
                checked={data.terms}
                onChange={(e) => setData('terms', e.target.checked)}
              />
            </div>

            <div className="pt-2">
              <Button
                type="submit"
                variant="primary"
                size="lg"
                loading={processing}
                className="w-full"
                icon={<ArrowRight className="w-4 h-4" />}
                iconPosition="right"
              >
                Provision Workspace
              </Button>
            </div>
          </form>

          <div className="pt-4 border-t border-[var(--border-subtle)] text-center text-xs text-gray-400">
            Already have an active account?{' '}
            <Link href="/login" className="text-purple-400 hover:text-white font-semibold">
              Sign In
            </Link>
          </div>
        </Card>
      </div>
    </div>
  );
}
