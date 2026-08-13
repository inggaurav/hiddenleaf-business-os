import React from 'react';
import { usePage, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { Switch } from '@/Components/UI/Checkbox';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Package, Check, Sparkles, Layers, ShieldCheck } from 'lucide-react';

export default function ModulesIndex() {
  const { modules = [], isCompanyAdmin, isSuperAdmin } = usePage<any>().props;

  const handleToggle = (moduleName: string) => {
    router.post('/modules/toggle', { module_name: moduleName });
  };

  return (
    <AppShell title="Module Catalog">
      <div className="space-y-6">
        <SectionHeader
          title="App & Add-On Modules"
          description="Extend your workspace capabilities with specialized business engines (HRM, CRM, Lead Pipeline, Accounting, POS, and Task Management)."
          badge={<Badge variant="purple" size="sm">7 Core Bundled</Badge>}
        />

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {modules.map((mod: any) => {
            const isInstalled = mod.is_installed ?? true;
            const isEnabled = mod.is_enabled ?? true;

            return (
              <Card
                key={mod.name}
                level={isEnabled ? 1 : 0}
                className={`flex flex-col justify-between relative group ${isEnabled ? 'border-violet-500/40 shadow-lg shadow-violet-950/20' : 'opacity-85'}`}
              >
                <div className="space-y-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <div className="w-11 h-11 rounded-xl bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-300 shadow-inner">
                        <Package className="w-5 h-5" />
                      </div>
                      <div>
                        <h3 className="text-base font-bold text-white tracking-tight">{mod.name}</h3>
                        <span className="text-xs text-gray-400 font-mono">v{mod.version || '1.0.0'}</span>
                      </div>
                    </div>
                    <Badge variant={isEnabled ? 'success' : 'neutral'} size="sm" dot>
                      {isEnabled ? 'Enabled' : 'Disabled'}
                    </Badge>
                  </div>

                  <p className="text-xs text-gray-300 leading-relaxed min-h-[40px]">
                    {mod.description || 'Full-featured enterprise subsystem providing comprehensive operational workflow.'}
                  </p>

                  <div className="flex items-center gap-2 text-xs text-purple-300">
                    <ShieldCheck className="w-3.5 h-3.5 text-emerald-400" />
                    <span>Included in Active SaaS Plan</span>
                  </div>
                </div>

                {/* Card Footer with Switch */}
                <div className="pt-4 mt-4 border-t border-white/10 flex items-center justify-between">
                  <span className="text-xs text-gray-400">Workspace Activation</span>
                  <Switch
                    checked={isEnabled}
                    onChange={() => handleToggle(mod.name)}
                    disabled={!isCompanyAdmin && !isSuperAdmin}
                  />
                </div>
              </Card>
            );
          })}
        </div>
      </div>
    </AppShell>
  );
}
