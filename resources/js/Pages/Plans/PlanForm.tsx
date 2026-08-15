import React from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Check, Package } from 'lucide-react';

interface Props {
  mode: 'create' | 'edit';
}

export default function PlanForm({ mode }: Props) {
  const { plan, availableModules = [] } = usePage<any>().props;
  const editing = mode === 'edit';
  const form = useForm({
    name: plan?.name || '',
    description: plan?.description || '',
    package_price_monthly: plan?.package_price_monthly ?? 0,
    package_price_yearly: plan?.package_price_yearly ?? 0,
    price_per_user_monthly: plan?.price_per_user_monthly ?? 0,
    price_per_user_yearly: plan?.price_per_user_yearly ?? 0,
    price_per_storage_monthly: plan?.price_per_storage_monthly ?? 0,
    price_per_storage_yearly: plan?.price_per_storage_yearly ?? 0,
    number_of_users: plan?.number_of_users ?? 1,
    storage_limit: plan?.storage_limit ?? 0,
    workspace_limit: plan?.workspace_limit ?? 1,
    modules: Array.isArray(plan?.modules) ? plan.modules : [],
    trial: Boolean(plan?.trial),
    trial_days: plan?.trial_days ?? 0,
    free_plan: Boolean(plan?.free_plan),
    status: plan?.status === undefined ? true : Boolean(plan.status),
  });

  const toggleModule = (alias: string) => {
    const normalized = alias.toLowerCase();
    const modules = form.data.modules.includes(normalized)
      ? form.data.modules.filter((value: string) => value !== normalized)
      : [...form.data.modules, normalized];
    form.setData('modules', modules);
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    if (editing) form.put(`/plans/${plan.id}`);
    else form.post('/plans');
  };

  const field = (name: keyof typeof form.data, label: string, type = 'number') => (
    <label className="space-y-1.5">
      <span className="text-xs font-semibold text-[var(--text-secondary)]">{label}</span>
      <input
        type={type}
        value={String(form.data[name] ?? '')}
        onChange={(event) => form.setData(name as any, type === 'number' ? Number(event.target.value) : event.target.value as any)}
        className="w-full px-3 py-2.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none focus:border-purple-500/50"
      />
      {(form.errors as any)[name] && <span className="text-[11px] text-rose-400">{(form.errors as any)[name]}</span>}
    </label>
  );

  return (
    <AppShell title={editing ? 'Edit Plan' : 'Create Plan'} breadcrumbs={[{ label: 'Plans', href: '/plans' }, { label: editing ? 'Edit' : 'Create' }]}>
      <form onSubmit={submit} className="space-y-6">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h1 className="text-xl font-bold">{editing ? `Edit ${plan.name}` : 'Create Subscription Plan'}</h1>
            <p className="text-xs text-[var(--text-tertiary)] mt-1">Define pricing, limits and the exact modules customers receive.</p>
          </div>
          <div className="flex gap-2"><Link href="/plans"><Button type="button" variant="outline">Cancel</Button></Link><Button type="submit" variant="primary" disabled={form.processing}>{editing ? 'Save Plan' : 'Create Plan'}</Button></div>
        </div>

        <Card level={0} className="space-y-4">
          <h2 className="text-sm font-bold">Plan Identity & Limits</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {field('name', 'Plan Name', 'text')}
            {field('number_of_users', 'Included Users')}
            <label className="md:col-span-2 space-y-1.5"><span className="text-xs font-semibold text-[var(--text-secondary)]">Description</span><textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows={3} className="w-full px-3 py-2.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none focus:border-purple-500/50" /></label>
            {field('workspace_limit', 'Workspace Limit')}
            {field('storage_limit', 'Storage Limit (MB)')}
          </div>
        </Card>

        <Card level={0} className="space-y-4">
          <h2 className="text-sm font-bold">Pricing</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            {field('package_price_monthly', 'Monthly Package Price')}
            {field('package_price_yearly', 'Yearly Package Price')}
            {field('price_per_user_monthly', 'Per User / Month')}
            {field('price_per_user_yearly', 'Per User / Year')}
            {field('price_per_storage_monthly', 'Storage / Month')}
            {field('price_per_storage_yearly', 'Storage / Year')}
            {field('trial_days', 'Trial Days')}
          </div>
          <div className="flex flex-wrap gap-5 text-xs">
            {[['trial', 'Trial enabled'], ['free_plan', 'Free plan'], ['status', 'Plan active']].map(([key, label]) => (
              <label key={key} className="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={Boolean((form.data as any)[key])} onChange={(event) => form.setData(key as any, event.target.checked)} />{label}</label>
            ))}
          </div>
        </Card>

        <Card level={0} className="space-y-4">
          <div className="flex items-center justify-between gap-3">
            <div><h2 className="text-sm font-bold">Module Entitlements</h2><p className="text-xs text-[var(--text-tertiary)] mt-1">Only selected modules can be enabled by customers on this plan.</p></div>
            <Badge variant="purple" size="sm">{form.data.modules.length} Selected</Badge>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {availableModules.map((mod: any) => {
              const selected = form.data.modules.includes(String(mod.alias).toLowerCase());
              return (
                <button
                  key={mod.alias}
                  type="button"
                  onClick={() => toggleModule(mod.alias)}
                  className={`text-left p-3 rounded-xl border transition-colors ${selected ? 'border-purple-500/50 bg-purple-500/10' : 'border-[var(--border-subtle)] bg-[var(--surface-2)] hover:border-purple-500/30'}`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2 min-w-0"><Package className="w-4 h-4 text-purple-400 flex-shrink-0" /><span className="text-xs font-semibold truncate">{mod.name}</span></div>
                    {selected && <Check className="w-4 h-4 text-emerald-400" />}
                  </div>
                  <div className="mt-1 text-[10px] text-[var(--text-tertiary)]">{mod.alias} · {mod.type}</div>
                </button>
              );
            })}
          </div>
        </Card>
      </form>
    </AppShell>
  );
}
