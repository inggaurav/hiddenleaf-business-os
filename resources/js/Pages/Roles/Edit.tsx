import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Checkbox } from '@/Components/UI/Checkbox';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function RolesEdit() {
  const { role, availablePermissions = [], rolePermissions = [] } = usePage<any>().props;

  const { data, setData, put, processing, errors } = useForm({
    name: role?.name || '',
    permissions: (rolePermissions || []).map((p: any) => p.name || p),
  });

  const handleToggle = (permName: string) => {
    if (data.permissions.includes(permName)) {
      setData('permissions', data.permissions.filter((p: string) => p !== permName));
    } else {
      setData('permissions', [...data.permissions, permName]);
    }
  };

  const handleSelectAll = () => {
    if (data.permissions.length === availablePermissions.length) {
      setData('permissions', []);
    } else {
      setData('permissions', availablePermissions.map((p: any) => p.name || p));
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/roles/${role.id}`);
  };

  return (
    <AppShell title={`Edit Role: ${role?.name}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Role: ${role?.name}`}
          description="Update role title and security permission entitlements."
          actions={
            <Link href="/roles">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Roles
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit} className="space-y-6">
          <Card level={0} className="space-y-4">
            <Input
              label="Role Title"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              error={errors.name}
              required
            />
          </Card>

          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-[var(--border-subtle)]">
              <div>
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  Permission Matrix
                </h3>
                <p className="text-xs text-gray-400">Select capabilities granted to this role</p>
              </div>
              <Button type="button" variant="secondary" size="sm" onClick={handleSelectAll}>
                {data.permissions.length === availablePermissions.length ? 'Deselect All' : 'Select All'}
              </Button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-96 overflow-y-auto pr-1">
              {availablePermissions.map((perm: any) => {
                const permName = perm.name || perm;
                const isChecked = data.permissions.includes(permName);

                return (
                  <div
                    key={permName}
                    onClick={() => handleToggle(permName)}
                    className={`
                      p-3 rounded-xl border text-xs cursor-pointer spring-transition select-none
                      ${isChecked ? 'bg-purple-600/20 border-purple-500/40 text-white font-medium' : 'bg-white/[0.02] border-white/5 text-gray-400 hover:bg-white/[0.05]'}
                    `}
                  >
                    <Checkbox
                      checked={isChecked}
                      onChange={() => {}}
                      label={permName.replace(/[._]/g, ' ')}
                    />
                  </div>
                );
              })}
            </div>

            <div className="pt-4 border-t border-[var(--border-subtle)] flex items-center justify-end gap-2">
              <Link href="/roles">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Update Role
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
