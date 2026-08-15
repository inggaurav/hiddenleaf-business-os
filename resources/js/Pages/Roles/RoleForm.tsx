import React from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { ArrowLeft, Check, Save } from 'lucide-react';

export default function RoleForm({ mode }: { mode: 'create' | 'edit' }) {
  const { role, permissions = [] } = usePage<any>().props;
  const editing = mode === 'edit';
  const selected = editing && Array.isArray(role?.permissions) ? role.permissions.map((permission: any) => permission.id) : [];
  const form = useForm({
    name: role?.name || '',
    display_name: role?.display_name || '',
    permissions: selected as number[],
  });

  const grouped = React.useMemo(() => permissions.reduce((result: Record<string, any[]>, permission: any) => {
    const key = permission.module || 'core';
    (result[key] ||= []).push(permission);
    return result;
  }, {}), [permissions]);

  const toggle = (id: number) => {
    form.setData('permissions', form.data.permissions.includes(id)
      ? form.data.permissions.filter((value) => value !== id)
      : [...form.data.permissions, id]);
  };

  const toggleGroup = (items: any[]) => {
    const ids = items.map((item) => item.id);
    const allSelected = ids.every((id) => form.data.permissions.includes(id));
    form.setData('permissions', allSelected
      ? form.data.permissions.filter((id) => !ids.includes(id))
      : Array.from(new Set([...form.data.permissions, ...ids])));
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    if (editing) form.put(`/roles/${role.id}`);
    else form.post('/roles');
  };

  return (
    <AppShell title={editing ? 'Edit Role' : 'Create Role'} breadcrumbs={[{ label: 'Team & Access' }, { label: 'Roles', href: '/roles' }, { label: editing ? 'Edit' : 'Create' }]}>
      <div className="max-w-6xl mx-auto space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div><h1 className="text-xl font-bold">{editing ? `Edit ${role.display_name || role.name}` : 'Create Custom Role'}</h1><p className="text-xs text-[var(--text-tertiary)] mt-1">Grant only the module/resource actions this team role needs.</p></div>
          <Link href="/roles"><Button variant="outline" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>Back to Roles</Button></Link>
        </div>

        <form onSubmit={submit} className="space-y-5">
          <Card level={0} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label className="space-y-1.5"><span className="text-xs font-semibold">Internal Role Name</span><input value={form.data.name} disabled={editing} onChange={(e) => form.setData('name', e.target.value)} placeholder="sales-manager" className="w-full px-3 py-2.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none" />{form.errors.name && <span className="text-[11px] text-rose-400">{form.errors.name}</span>}</label>
            <label className="space-y-1.5"><span className="text-xs font-semibold">Display Name</span><input value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} placeholder="Sales Manager" className="w-full px-3 py-2.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none" />{form.errors.display_name && <span className="text-[11px] text-rose-400">{form.errors.display_name}</span>}</label>
          </Card>

          <div className="flex items-center justify-between"><div><h2 className="text-sm font-bold">Permission Matrix</h2><p className="text-xs text-[var(--text-tertiary)]">Grouped by module for WorkDo-style administration.</p></div><Badge variant="purple" size="sm">{form.data.permissions.length} Granted</Badge></div>

          <div className="space-y-4">
            {Object.entries(grouped).sort(([a], [b]) => a.localeCompare(b)).map(([module, items]: any) => {
              const allSelected = items.every((permission: any) => form.data.permissions.includes(permission.id));
              return (
                <Card key={module} level={0} className="space-y-3">
                  <div className="flex items-center justify-between gap-3 border-b border-[var(--border-subtle)] pb-3"><div><h3 className="text-sm font-bold capitalize">{String(module).replaceAll('_', ' ')}</h3><p className="text-[11px] text-[var(--text-tertiary)]">{items.length} permission{items.length === 1 ? '' : 's'}</p></div><Button type="button" variant="outline" size="sm" onClick={() => toggleGroup(items)}>{allSelected ? 'Clear Module' : 'Grant Module'}</Button></div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                    {items.map((permission: any) => {
                      const checked = form.data.permissions.includes(permission.id);
                      return (
                        <button key={permission.id} type="button" onClick={() => toggle(permission.id)} className={`p-3 rounded-xl border text-left transition-colors ${checked ? 'bg-purple-500/10 border-purple-500/40' : 'bg-white/[0.02] border-[var(--border-subtle)] hover:border-purple-500/20'}`}>
                          <div className="flex items-start justify-between gap-2"><div><div className="text-xs font-semibold">{permission.resource || permission.name}</div><div className="text-[10px] text-[var(--text-tertiary)] mt-1">{permission.action || permission.name}</div></div>{checked && <Check className="w-4 h-4 text-emerald-400 flex-shrink-0" />}</div>
                        </button>
                      );
                    })}
                  </div>
                </Card>
              );
            })}
          </div>

          <div className="flex justify-end gap-2"><Link href="/roles"><Button type="button" variant="outline">Cancel</Button></Link><Button type="submit" variant="primary" loading={form.processing} icon={<Save className="w-4 h-4" />}>{editing ? 'Update Role' : 'Create Role'}</Button></div>
        </form>
      </div>
    </AppShell>
  );
}
