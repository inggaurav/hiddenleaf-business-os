import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Checkbox } from '@/Components/UI/Checkbox';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, ShieldCheck } from 'lucide-react';

export default function RoleCreate() {
  const { permissions = [] } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    name: '',
    display_name: '',
    permissions: [] as number[],
  });

  const handleTogglePermission = (id: number) => {
    const exists = data.permissions.includes(id);
    if (exists) {
      setData('permissions', data.permissions.filter((p) => p !== id));
    } else {
      setData('permissions', [...data.permissions, id]);
    }
  };

  const handleSelectAll = () => {
    if (data.permissions.length === permissions.length) {
      setData('permissions', []);
    } else {
      setData('permissions', permissions.map((p: any) => p.id));
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/roles');
  };

  return (
    <AppShell title="Create Role">
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title="Create Custom Role"
          description="Define a new organizational role and assign granular capability permissions."
          actions={
            <Link href="/roles">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Roles
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-6">
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
                Role Identity
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Role Display Name"
                  placeholder="e.g. Finance Analyst"
                  value={data.display_name}
                  onChange={(e) => {
                    setData((prev) => ({
                      ...prev,
                      display_name: e.target.value,
                      name: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
                    }));
                  }}
                  error={errors.display_name}
                  required
                />
                <Input
                  label="System Key (Identifier)"
                  placeholder="e.g. finance-analyst"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  error={errors.name}
                  required
                />
              </div>
            </div>

            {/* Permission Matrix */}
            <div className="pt-4 border-t border-white/10 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
                    Permissions ({data.permissions.length}/{permissions.length})
                  </h3>
                  <p className="text-xs text-gray-400 mt-0.5">Toggle privileges permitted for members holding this role.</p>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={handleSelectAll}>
                  {data.permissions.length === permissions.length ? 'Deselect All' : 'Select All'}
                </Button>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4 rounded-xl bg-white/[0.02] border border-white/10 max-h-96 overflow-y-auto">
                {permissions.map((perm: any) => {
                  const checked = data.permissions.includes(perm.id);
                  return (
                    <div
                      key={perm.id}
                      onClick={() => handleTogglePermission(perm.id)}
                      className={`
                        p-2.5 rounded-lg border text-xs spring-transition cursor-pointer flex items-center justify-between select-none
                        ${checked ? 'bg-violet-600/20 border-violet-500/40 text-white' : 'bg-white/[0.03] border-white/10 text-gray-400 hover:text-white'}
                      `}
                    >
                      <span className="font-medium truncate">{perm.name}</span>
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => {}}
                        className="rounded border-white/20 text-violet-600 focus:ring-0 ml-2 pointer-events-none"
                      />
                    </div>
                  );
                })}
              </div>
            </div>

            <div className="pt-6 border-t border-white/10 flex items-center justify-end gap-3">
              <Link href="/roles">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Role
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
