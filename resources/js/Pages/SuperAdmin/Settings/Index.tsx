import React from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Save, ShieldCheck, HardDrive } from 'lucide-react';

export default function SuperAdminSettings() {
  const { settings = {} } = usePage<any>().props;

  const { data, setData, post, processing } = useForm({
    app_name: settings.app_name || 'HiddenLeaf BusinessOS',
    footer_text: settings.footer_text || '© 2026 HiddenLeaf Inc. All rights reserved.',
    default_storage_limit: settings.default_storage_limit || '1024',
    max_workspaces_per_tenant: settings.max_workspaces_per_tenant || '5',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/super-admin/settings');
  };

  return (
    <AppShell title="Super Admin Global Settings">
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title="Global SaaS Configuration"
          description="Master parameters governing all tenant organizations and root platform branding."
          badge={<Badge variant="purple" size="sm">Root Master</Badge>}
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <Input
              label="Global Application Title"
              value={data.app_name}
              onChange={(e) => setData('app_name', e.target.value)}
              required
            />
            <Input
              label="Footer Notice"
              value={data.footer_text}
              onChange={(e) => setData('footer_text', e.target.value)}
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Default Storage Allocation (MB)"
                type="number"
                value={data.default_storage_limit}
                onChange={(e) => setData('default_storage_limit', e.target.value)}
              />
              <Input
                label="Max Workspaces per Tenant"
                type="number"
                value={data.max_workspaces_per_tenant}
                onChange={(e) => setData('max_workspaces_per_tenant', e.target.value)}
              />
            </div>

            <div className="pt-4 flex items-center justify-end">
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Master Settings
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
