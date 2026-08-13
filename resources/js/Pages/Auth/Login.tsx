import React, { useState } from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Button } from '@ui/Button';

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
    <div className="min-h-screen flex items-center justify-center bg-slate-950 px-4">
      <div className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-xl p-8 shadow-xl">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold text-slate-100">HiddenLeaf BusinessOS</h1>
          <p className="text-sm text-slate-400 mt-1">Sign in to your account</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label className="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address</label>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-100 focus:outline-none focus:border-emerald-500 text-sm"
              placeholder="admin@hiddenleaf.io"
              required
            />
            {errors.email && <p className="text-xs text-rose-400 mt-1.5">{errors.email}</p>}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
            <input
              type="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-100 focus:outline-none focus:border-emerald-500 text-sm"
              required
            />
            {errors.password && <p className="text-xs text-rose-400 mt-1.5">{errors.password}</p>}
          </div>

          <div className="flex items-center justify-between text-xs">
            <label className="flex items-center text-slate-400">
              <input
                type="checkbox"
                checked={data.remember}
                onChange={(e) => setData('remember', e.target.checked)}
                className="rounded border-slate-800 bg-slate-950 text-emerald-600 focus:ring-emerald-500 mr-2"
              />
              Remember me
            </label>
            <Link href="/forgot-password" className="text-emerald-400 hover:underline">Forgot password?</Link>
          </div>

          <Button type="submit" isLoading={processing} className="w-full">
            Sign In
          </Button>

          <p className="text-center text-xs text-slate-400 mt-4">
            Don't have an account? <Link href="/register" className="text-emerald-400 hover:underline">Register</Link>
          </p>
        </form>
      </div>
    </div>
  );
}
