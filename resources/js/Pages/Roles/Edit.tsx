import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Button } from '@ui/Button';

export default function Edit({ role, permissions }: { role: { id: number; display_name: string; permissions: Array<{ id: number }> }; permissions: Array<{ id: number; name: string }> }) {
  const { data, setData, put, processing, errors } = useForm({
    display_name: role.display_name,
    permissions: role.permissions.map((p) => p.id),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/roles/${role.id}`);
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-8">
      <div className="max-w-xl mx-auto bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h1 className="text-xl font-bold mb-4">Edit Role</h1>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider mb-2">Display Name</label>
            <input
              type="text"
              value={data.display_name}
              onChange={(e) => setData('display_name', e.target.value)}
              className="w-full px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-100"
              required
            />
          </div>
          <div className="flex justify-between items-center pt-2">
            <Link href="/roles" className="text-xs text-slate-400 hover:underline">Cancel</Link>
            <Button type="submit" isLoading={processing}>Save Role</Button>
          </div>
        </form>
      </div>
    </div>
  );
}
