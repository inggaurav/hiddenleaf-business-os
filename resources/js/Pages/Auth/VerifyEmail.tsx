import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Button } from '@ui/Button';

export default function VerifyEmail() {
  const { post, processing } = useForm({});

  const handleResend = (e: React.FormEvent) => {
    e.preventDefault();
    post('/email/verification-notification');
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-950 px-4">
      <div className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-xl p-8 shadow-xl text-center">
        <h1 className="text-xl font-bold text-slate-100 mb-2">Verify Your Email</h1>
        <p className="text-xs text-slate-400 mb-6">Thanks for signing up! Please check your inbox and click the verification link.</p>
        <form onSubmit={handleResend} className="space-y-4">
          <Button type="submit" isLoading={processing} className="w-full">
            Resend Verification Email
          </Button>
          <Link href="/logout" method="post" as="button" className="text-xs text-slate-400 hover:underline">
            Log Out
          </Link>
        </form>
      </div>
    </div>
  );
}
