import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Button } from '@ui/Button';

export default function Create({ permissions }: { permissions: Array<{ id: number; name: string }> }) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    display_name: '',
    permissions: [] as number[],
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/roles');
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-8">
      <div className="max-w-xl mx-auto bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h1 className="text-xl font-bold mb-4">Create New Role</h1>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider mb-2">System Name (Identifier)</label>
            <input
              type="text"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              className="w-full px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-100"
              placeholder="e.g. project-manager"
              required
            />
          </div>
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider mb-2">Display Name</label>
            <input
              type="text"
              value={data.display_name}
              onChange={(e) => setData('display_name', e.target.value)}
              className="w-full px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-100"
              placeholder="e.g. Project Manager"
              required
            />
          </div>
          <div className="flex justify-between items-center pt-2">
            <Link href="/roles" className="text-xs text-slate-400 hover:underline">Cancel</Link>
            <Button type="submit" isLoading={processing}>Create Role</Button>
          </div>
        </form>
      </div>
    </div>
  );
}
