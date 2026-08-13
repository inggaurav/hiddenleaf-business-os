import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { MailCheck, LogOut } from 'lucide-react';

export default function VerifyEmail({ status }: { status?: string }) {
  const { post, processing } = useForm();

  const handleResend = (e: React.FormEvent) => {
    e.preventDefault();
    post('/email/verification-notification');
  };

  return (
    <div className="min-h-screen bg-[var(--bg-0)] text-[var(--text-primary)] flex items-center justify-center p-4 relative overflow-hidden">
      <div className="max-w-md w-full relative z-10 space-y-6">
        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white mx-auto shadow-xl">
            <MailCheck className="w-6 h-6" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">Verify Your Email</h1>
          <p className="text-xs text-gray-400">
            Thanks for signing up! Before getting started, please verify your email address by clicking on the link we sent to your inbox.
          </p>
        </div>

        <Card level={1} className="p-6 sm:p-8 space-y-5">
          {status === 'verification-link-sent' && (
            <div className="p-3 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-medium">
              A new verification link has been sent to your email address.
            </div>
          )}

          <form onSubmit={handleResend} className="space-y-4">
            <Button
              type="submit"
              variant="primary"
              size="lg"
              loading={processing}
              className="w-full"
            >
              Resend Verification Email
            </Button>
          </form>

          <div className="pt-4 border-t border-[var(--border-subtle)] text-center">
            <Link href="/logout" method="post" as="button" className="text-xs text-gray-400 hover:text-rose-400 inline-flex items-center gap-1.5 cursor-pointer">
              <LogOut className="w-3.5 h-3.5" /> Sign Out
            </Link>
          </div>
        </Card>
      </div>
    </div>
  );
}
