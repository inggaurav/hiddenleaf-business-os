import React, { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Eye, EyeOff, Lock, Mail } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Checkbox } from '@/Components/UI/Checkbox';

export default function Login() {
  const { flash = {} } = usePage<{ flash?: { success?: string; error?: string } }>().props;
  const [showPassword, setShowPassword] = useState(false);
  const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: false });
  const installationComplete = Boolean(flash.success?.toLowerCase().includes('installation completed'));

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    post('/login', { preserveScroll: true });
  };

  return (
    <GuestLayout title={installationComplete ? 'BusinessOS is ready' : 'Welcome back'} eyebrow="HiddenLeaf BusinessOS" description={installationComplete ? 'Your secure installation completed successfully. Sign in with the administrator account you created.' : 'Sign in to continue to your secure business workspace.'}>
      {installationComplete && (
        <div role="status" className="mb-4 rounded-2xl border border-emerald-400/25 bg-emerald-400/[0.08] p-5">
          <div className="flex items-start gap-3">
            <CheckCircle2 className="mt-0.5 h-6 w-6 shrink-0 text-emerald-400" aria-hidden="true" />
            <div>
              <p className="font-semibold text-emerald-100">HiddenLeaf BusinessOS is ready.</p>
              <p className="mt-1 text-sm leading-6 text-emerald-200/65">The installer is now locked. Use your administrator credentials below to open BusinessOS.</p>
            </div>
          </div>
          <a href="#sign-in-form" className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-emerald-300 hover:text-emerald-200 focus-ring">Sign In <ArrowRight className="h-4 w-4" /></a>
        </div>
      )}

      <Card level={1} className="border-white/[0.09] bg-[#0c1118]/90 p-6 shadow-2xl shadow-black/30 sm:p-8">
        {flash.error && <div role="alert" className="mb-5 rounded-xl border border-rose-400/25 bg-rose-400/[0.08] p-3 text-sm text-rose-200">{flash.error}</div>}
        <form id="sign-in-form" onSubmit={handleSubmit} className="space-y-5">
          <Input
            label="Email address"
            type="email"
            inputMode="email"
            placeholder="admin@company.com"
            leftIcon={<Mail className="h-4 w-4" />}
            value={data.email}
            onChange={(event) => setData('email', event.target.value)}
            error={errors.email}
            autoComplete="email"
            autoFocus
            required
          />
          <Input
            label="Password"
            type={showPassword ? 'text' : 'password'}
            placeholder="Enter your password"
            leftIcon={<Lock className="h-4 w-4" />}
            rightIcon={<button type="button" onClick={() => setShowPassword((visible) => !visible)} className="rounded p-1 hover:text-white focus-ring" aria-label={showPassword ? 'Hide password' : 'Show password'}>{showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}</button>}
            value={data.password}
            onChange={(event) => setData('password', event.target.value)}
            error={errors.password}
            autoComplete="current-password"
            required
          />
          <div className="flex items-center justify-between gap-4 text-xs">
            <Checkbox label="Remember me" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} />
            <Link href="/forgot-password" className="font-medium text-emerald-400 hover:text-emerald-300 focus-ring">Forgot password?</Link>
          </div>
          <Button type="submit" variant="primary" size="lg" loading={processing} className="w-full" icon={<ArrowRight className="h-4 w-4" />} iconPosition="right">
            Sign In to BusinessOS
          </Button>
        </form>
        <div className="mt-6 border-t border-white/[0.07] pt-5 text-center text-xs text-slate-500">
          Need a new workspace? <Link href="/register" className="font-semibold text-emerald-400 hover:text-emerald-300 focus-ring">Create one</Link>
        </div>
      </Card>
      <p className="mt-5 text-center text-xs text-slate-600">Protected by tenant isolation and role-based access controls.</p>
    </GuestLayout>
  );
}
