import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Check, CreditCard, Plus, Users, Boxes, Layers } from 'lucide-react';

function money(value: any) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(value || 0));
}

export default function PlansIndex() {
  const { plans = [], canCreate = false } = usePage<any>().props;

  return (
    <AppShell title="Plans" breadcrumbs={[{ label: 'Plans & Billing' }, { label: 'Plans' }]}>
      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h1 className="text-xl font-bold">Subscription Plans</h1>
            <p className="text-xs text-[var(--text-tertiary)] mt-1">Pricing, limits and module entitlements for customer workspaces.</p>
          </div>
          {canCreate && <Link href="/plans/create"><Button variant="primary" icon={<Plus className="w-4 h-4" />}>Create Plan</Button></Link>}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-5">
          {plans.map((plan: any) => {
            const modules: string[] = Array.isArray(plan.modules) ? plan.modules : [];
            return (
              <Card key={plan.id} level={0} className="flex flex-col justify-between gap-5">
                <div className="space-y-4">
                  <div className="flex items-start justify-between gap-3">
                    <div><h2 className="text-base font-bold">{plan.name}</h2><p className="text-xs text-[var(--text-tertiary)] mt-1">{plan.description || 'Business subscription plan'}</p></div>
                    <Badge variant={plan.status ? 'success' : 'neutral'} size="sm" dot>{plan.status ? 'Active' : 'Inactive'}</Badge>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div className="p-3 rounded-xl bg-white/[0.03] border border-[var(--border-subtle)]"><div className="text-[10px] text-[var(--text-tertiary)] uppercase">Monthly</div><div className="font-bold mt-1">{money(plan.package_price_monthly)}</div></div>
                    <div className="p-3 rounded-xl bg-white/[0.03] border border-[var(--border-subtle)]"><div className="text-[10px] text-[var(--text-tertiary)] uppercase">Yearly</div><div className="font-bold mt-1">{money(plan.package_price_yearly)}</div></div>
                  </div>

                  <div className="grid grid-cols-3 gap-2 text-[11px] text-[var(--text-secondary)]">
                    <div className="flex items-center gap-1.5"><Users className="w-3.5 h-3.5 text-purple-400" />{plan.number_of_users} users</div>
                    <div className="flex items-center gap-1.5"><Layers className="w-3.5 h-3.5 text-purple-400" />{plan.workspace_limit} workspace{Number(plan.workspace_limit) === 1 ? '' : 's'}</div>
                    <div className="flex items-center gap-1.5"><Boxes className="w-3.5 h-3.5 text-purple-400" />{modules.length} modules</div>
                  </div>

                  <div className="space-y-2">
                    <div className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Included Modules</div>
                    <div className="flex flex-wrap gap-1.5">
                      {modules.length ? modules.map((module) => <span key={module} className="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-purple-500/10 border border-purple-500/20 text-[10px] text-purple-300"><Check className="w-3 h-3" />{module}</span>) : <span className="text-[11px] text-amber-300">No modules assigned</span>}
                    </div>
                  </div>
                </div>

                <div className="pt-4 border-t border-[var(--border-subtle)] flex items-center justify-between gap-2">
                  <div className="flex gap-1.5">{plan.free_plan && <Badge variant="success" size="sm">Free</Badge>}{plan.trial && <Badge variant="purple" size="sm">{plan.trial_days}d Trial</Badge>}</div>
                  <div className="flex gap-2">
                    {canCreate ? <Link href={`/plans/${plan.id}/edit`}><Button variant="outline" size="sm">Edit</Button></Link> : <Link href={`/plans/${plan.id}/subscribe`}><Button variant="primary" size="sm" icon={<CreditCard className="w-3.5 h-3.5" />}>Choose</Button></Link>}
                  </div>
                </div>
              </Card>
            );
          })}
        </div>
      </div>
    </AppShell>
  );
}
