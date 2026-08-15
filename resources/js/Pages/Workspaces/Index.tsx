import React from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Layers, Plus, Pencil, Trash2 } from 'lucide-react';

export default function WorkspacesIndex() {
  const { workspaces = [], workspaceLimit = null, canCreate = false, tenant } = usePage<any>().props;
  const form = useForm({ name: '' });
  const [creating, setCreating] = React.useState(false);
  const activeId = Number(tenant?.workspace_id || 0);

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.post('/workspaces', { onSuccess: () => { form.reset(); setCreating(false); } });
  };

  return (
    <AppShell title="Workspaces" breadcrumbs={[{ label: 'Team & Access' }, { label: 'Workspaces' }]}>
      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div><h1 className="text-xl font-bold">Workspaces</h1><p className="text-xs text-[var(--text-tertiary)] mt-1">Separate business contexts with their own modules, permissions and data.</p></div>
          <div className="flex items-center gap-2">{workspaceLimit !== null && <Badge variant="neutral" size="sm">{workspaces.length}/{workspaceLimit} used</Badge>}{canCreate && <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />} onClick={() => setCreating((value) => !value)}>New Workspace</Button>}</div>
        </div>

        {creating && (
          <Card level={0} className="max-w-xl">
            <form onSubmit={submit} className="flex flex-col sm:flex-row gap-3"><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Workspace name" className="flex-1 px-3 py-2.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none" required /><div className="flex gap-2"><Button type="button" variant="outline" onClick={() => setCreating(false)}>Cancel</Button><Button type="submit" variant="primary" loading={form.processing}>Create</Button></div></form>{form.errors.name && <div className="mt-2 text-xs text-rose-400">{form.errors.name}</div>}
          </Card>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {workspaces.map((workspace: any) => {
            const active = Number(workspace.id) === activeId;
            return (
              <Card key={workspace.id} level={active ? 1 : 0} className={active ? 'border-purple-500/40' : ''}>
                <div className="flex items-start justify-between gap-3"><div className="flex items-center gap-3 min-w-0"><div className="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center"><Layers className="w-5 h-5 text-purple-400" /></div><div className="min-w-0"><div className="font-bold text-sm truncate">{workspace.name}</div><div className="text-[11px] text-[var(--text-tertiary)] truncate">{workspace.slug}</div></div></div><Badge variant={active ? 'purple' : (workspace.is_active ? 'success' : 'neutral')} size="sm">{active ? 'Current' : (workspace.is_active ? 'Active' : 'Inactive')}</Badge></div>
                <div className="mt-4 pt-4 border-t border-[var(--border-subtle)] flex items-center justify-between gap-2">
                  {!active ? <Button size="sm" variant="primary" onClick={() => router.post('/workspaces/switch', { workspace_id: workspace.id })}>Switch</Button> : <span className="text-[11px] text-purple-300">Current workspace</span>}
                  <div className="flex gap-1"><Link href={`/workspaces/${workspace.id}/edit`}><Button size="sm" variant="ghost" icon={<Pencil className="w-3.5 h-3.5" />}>Edit</Button></Link>{workspaces.length > 1 && <Button size="sm" variant="ghost" icon={<Trash2 className="w-3.5 h-3.5 text-rose-400" />} onClick={() => confirm(`Delete ${workspace.name}?`) && router.delete(`/workspaces/${workspace.id}`)}>Delete</Button>}</div>
                </div>
              </Card>
            );
          })}
        </div>
      </div>
    </AppShell>
  );
}
