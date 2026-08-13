import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Button } from '@ui/Button';

export default function Edit({ workspace }: { workspace: { id: number; name: string } }) {
  const { data, setData, put, processing, errors } = useForm({
    name: workspace.name,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/workspaces/${workspace.id}`);
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-8">
      <div className="max-w-xl mx-auto bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h1 className="text-xl font-bold mb-4">Edit Workspace</h1>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider mb-2">Workspace Name</label>
            <input
              type="text"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-100"
              required
            />
            {errors.name && <p className="text-xs text-rose-400 mt-1">{errors.name}</p>}
          </div>

          <div className="flex justify-between items-center pt-2">
            <Link href="/workspaces" className="text-xs text-slate-400 hover:underline">Cancel</Link>
            <Button type="submit" isLoading={processing}>Save Changes</Button>
          </div>
        </form>
      </div>
    </div>
  );
}
