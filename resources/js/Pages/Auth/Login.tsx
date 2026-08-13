import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Checkbox } from '@/Components/UI/Checkbox';
import { Badge } from '@/Components/UI/Badge';
import { Sparkles, Lock, Mail, ArrowRight } from 'lucide-react';

export default function Login() {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/login');
  };

  return (
    <div className="min-h-screen bg-[#060709] text-gray-100 flex items-center justify-center p-4 relative overflow-hidden">
      {/* Subtle Background Glow */}
      <div className="absolute -top-40 -left-40 w-96 h-96 bg-purple-900/20 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute -bottom-40 -right-40 w-96 h-96 bg-indigo-900/20 rounded-full blur-3xl pointer-events-none" />

      <div className="max-w-md w-full relative z-10 space-y-6">
        {/* Brand Header */}
        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-xl shadow-purple-950/40 mx-auto">
            HL
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">HiddenLeaf BusinessOS</h1>
          <p className="text-xs text-gray-400">Sign in to your enterprise tenant workspace</p>
        </div>

        {/* Login Surface */}
        <Card level={1} className="p-6 sm:p-8 space-y-5">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label="Email Address"
              type="email"
              placeholder="admin@company.com"
              leftIcon={<Mail className="w-4 h-4 text-gray-400" />}
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              error={errors.email}
              required
            />

            <Input
              label="Password"
              type="password"
              placeholder="••••••••"
              leftIcon={<Lock className="w-4 h-4 text-gray-400" />}
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              error={errors.password}
              required
            />

            <div className="flex items-center justify-between text-xs pt-1">
              <Checkbox
                label="Remember session"
                checked={data.remember}
                onChange={(e) => setData('remember', e.target.checked)}
              />
              <Link href="/forgot-password" className="text-violet-400 hover:text-violet-300 font-medium">
                Forgot password?
              </Link>
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
                Sign In to BusinessOS
              </Button>
            </div>
          </form>

          <div className="pt-4 border-t border-white/10 text-center text-xs text-gray-400">
            Don't have an enterprise account?{' '}
            <Link href="/register" className="text-violet-400 hover:text-white font-semibold">
              Register Workspace
            </Link>
          </div>
        </Card>
      </div>
    </div>
  );
}
