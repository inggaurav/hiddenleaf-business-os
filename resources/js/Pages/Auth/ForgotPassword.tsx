import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Button } from '@ui/Button';

export default function ForgotPassword() {
  const { data, setData, post, processing, errors } = useForm({ email: '' });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/forgot-password');
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-950 px-4">
      <div className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-xl p-8 shadow-xl">
        <div className="text-center mb-6">
          <h1 className="text-xl font-bold text-slate-100">Reset Password</h1>
          <p className="text-xs text-slate-400 mt-1">Enter your account email to receive a reset link</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address</label>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-100 focus:outline-none focus:border-emerald-500 text-sm"
              required
            />
            {errors.email && <p className="text-xs text-rose-400 mt-1.5">{errors.email}</p>}
          </div>

          <Button type="submit" isLoading={processing} className="w-full">
            Send Reset Link
          </Button>

          <p className="text-center text-xs text-slate-400 mt-4">
            <Link href="/login" className="text-emerald-400 hover:underline">Back to Login</Link>
          </p>
        </form>
      </div>
    </div>
  );
}
