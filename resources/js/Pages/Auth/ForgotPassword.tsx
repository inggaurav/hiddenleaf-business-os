import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Mail, ArrowLeft, Send } from 'lucide-react';

export default function ForgotPassword({ status }: { status?: string }) {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/forgot-password');
  };

  return (
    <div className="min-h-screen bg-[#060709] text-gray-100 flex items-center justify-center p-4 relative overflow-hidden">
      <div className="max-w-md w-full relative z-10 space-y-6">
        <div className="text-center space-y-2">
          <h1 className="text-2xl font-bold tracking-tight text-white">Reset Password</h1>
          <p className="text-xs text-gray-400">Enter your email to receive a signed password reset link</p>
        </div>

        <Card level={1} className="p-6 sm:p-8 space-y-5">
          {status && (
            <div className="p-3 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-medium">
              {status}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
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

            <div className="pt-2">
              <Button
                type="submit"
                variant="primary"
                size="lg"
                loading={processing}
                className="w-full"
                icon={<Send className="w-4 h-4" />}
              >
                Send Password Reset Link
              </Button>
            </div>
          </form>

          <div className="pt-4 border-t border-white/10 text-center text-xs">
            <Link href="/login" className="text-gray-400 hover:text-white inline-flex items-center gap-1">
              <ArrowLeft className="w-3.5 h-3.5" /> Back to Sign In
            </Link>
          </div>
        </Card>
      </div>
    </div>
  );
}
