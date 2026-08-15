import React from 'react';
import { router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Building2, Search, Users, Layers } from 'lucide-react';

export default function CompaniesIndex() {
  const { companies, plans = [], filters = {} } = usePage<any>().props;
  const [search, setSearch] = React.useState(filters.search || '');

  const submitSearch = (event: React.FormEvent) => {
    event.preventDefault();
    router.get('/super-admin/companies', { search }, { preserveState: true, replace: true });
  };

  const updateCompany = (company: any, patch: any) => {
    router.patch(`/super-admin/companies/${company.id}`, {
      plan_id: patch.plan_id ?? company.plan_id ?? null,
      is_active: patch.is_active ?? Boolean(company.is_active),
    }, { preserveScroll: true });
  };

  return (
    <AppShell title="Companies" breadcrumbs={[{ label: 'Platform Administration' }, { label: 'Companies' }]}>
      <div className="space-y-6">
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
          <div><h1 className="text-xl font-bold">Companies & Tenants</h1><p className="text-xs text-[var(--text-tertiary)] mt-1">Manage customer companies, owners, workspaces, plan assignment and access state.</p></div>
          <form onSubmit={submitSearch} className="flex gap-2 w-full md:w-auto"><div className="relative flex-1 md:w-72"><Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-tertiary)]" /><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search companies or owners..." className="w-full pl-9 pr-3 py-2 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none" /></div><Button type="submit" variant="outline">Search</Button></form>
        </div>

        <div className="space-y-3">
          {(companies?.data || []).map((company: any) => (
            <Card key={company.id} level={0} className="grid grid-cols-1 xl:grid-cols-[1.4fr_.8fr_.8fr_.8fr_auto] gap-4 items-center">
              <div className="flex items-center gap-3 min-w-0">
                <div className="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center"><Building2 className="w-5 h-5 text-purple-400" /></div>
                <div className="min-w-0"><div className="font-bold text-sm truncate">{company.name}</div><div className="text-[11px] text-[var(--text-tertiary)] truncate">{company.owner?.name || 'No owner'} · {company.owner?.email || '—'}</div></div>
              </div>

              <div className="text-xs"><div className="text-[10px] uppercase text-[var(--text-tertiary)] mb-1">Usage</div><div className="flex gap-3"><span className="inline-flex items-center gap-1"><Layers className="w-3.5 h-3.5" />{company.workspaces_count} workspaces</span><span className="inline-flex items-center gap-1"><Users className="w-3.5 h-3.5" />{company.members_count} members</span></div></div>

              <label className="space-y-1"><span className="text-[10px] uppercase text-[var(--text-tertiary)]">Plan</span><select value={company.plan_id || ''} onChange={(e) => updateCompany(company, { plan_id: e.target.value ? Number(e.target.value) : null })} className="w-full px-2.5 py-2 rounded-lg bg-[var(--surface-2)] border border-[var(--border-subtle)] text-xs"><option value="">No plan</option>{plans.map((plan: any) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}</select></label>

              <div><div className="text-[10px] uppercase text-[var(--text-tertiary)] mb-1">Status</div><Badge variant={company.is_active ? 'success' : 'neutral'} size="sm" dot>{company.is_active ? 'Active' : 'Suspended'}</Badge></div>

              <Button variant={company.is_active ? 'outline' : 'primary'} size="sm" onClick={() => updateCompany(company, { is_active: !company.is_active })}>{company.is_active ? 'Suspend' : 'Activate'}</Button>
            </Card>
          ))}
        </div>
      </div>
    </AppShell>
  );
}
