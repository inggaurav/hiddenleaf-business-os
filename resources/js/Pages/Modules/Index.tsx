import React from 'react';
import { router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Switch } from '@/Components/UI/Checkbox';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Package, ShieldCheck, Workflow } from 'lucide-react';

export default function ModulesIndex() {
  const { modules = [], isSuperAdmin = false, canManage = false } = usePage<any>().props;

  const handleToggle = (mod: any) => {
    router.post('/modules/toggle', {
      module_name: mod.alias || mod.name,
      active: !Boolean(mod.active),
    }, { preserveScroll: true });
  };

  return (
    <AppShell title="Module Catalog" breadcrumbs={[{ label: 'Administration' }, { label: 'Module Catalog' }]}>
      <div className="space-y-6">
        <SectionHeader
          title="Modules & Add-ons"
          description="Installed platform capabilities, plan entitlement, dependencies and workspace activation."
          badge={<Badge variant="purple" size="sm">{modules.length} Available</Badge>}
        />

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          {modules.map((mod: any) => {
            const active = Boolean(mod.active);
            const entitled = Boolean(mod.entitled);
            const dependencies: string[] = Array.isArray(mod.dependencies) ? mod.dependencies : [];
            const toggleDisabled = !canManage || (!active && !entitled && !isSuperAdmin);

            return (
              <Card key={mod.alias || mod.name} level={active ? 1 : 0} className={`flex flex-col justify-between ${active ? 'border-violet-500/40' : ''}`}>
                <div className="space-y-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                      <div className="w-11 h-11 rounded-xl bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-300 flex-shrink-0">
                        <Package className="w-5 h-5" />
                      </div>
                      <div className="min-w-0">
                        <h3 className="text-base font-bold text-white tracking-tight truncate">{mod.name}</h3>
                        <span className="text-xs text-gray-400 font-mono">{mod.alias} · v{mod.version || '1.0.0'}</span>
                      </div>
                    </div>
                    <Badge variant={active ? 'success' : 'neutral'} size="sm" dot>{active ? 'Enabled' : 'Disabled'}</Badge>
                  </div>

                  <p className="text-xs text-gray-300 leading-relaxed min-h-[40px]">{mod.description || 'Business module.'}</p>

                  <div className="space-y-2 text-xs">
                    <div className={`flex items-center gap-2 ${entitled || isSuperAdmin ? 'text-emerald-300' : 'text-amber-300'}`}>
                      <ShieldCheck className="w-3.5 h-3.5" />
                      <span>{entitled || isSuperAdmin ? 'Available to current plan' : 'Not included in current plan'}</span>
                    </div>
                    {dependencies.length > 0 && (
                      <div className="flex items-start gap-2 text-gray-400">
                        <Workflow className="w-3.5 h-3.5 mt-0.5 flex-shrink-0" />
                        <span>Requires: {dependencies.join(', ')}</span>
                      </div>
                    )}
                  </div>
                </div>

                <div className="pt-4 mt-4 border-t border-white/10 flex items-center justify-between gap-3">
                  <div>
                    <div className="text-xs text-gray-300">Workspace Activation</div>
                    <div className="text-[10px] text-gray-500">Data is preserved when disabled.</div>
                  </div>
                  <Switch checked={active} onChange={() => handleToggle(mod)} disabled={toggleDisabled} />
                </div>
              </Card>
            );
          })}
        </div>
      </div>
    </AppShell>
  );
}
