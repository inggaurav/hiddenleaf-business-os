import React from 'react';
import { useForm, usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function WorkspacesEdit() {
  const { workspace } = usePage<any>().props;

  const { data, setData, put, processing, errors } = useForm({
    name: workspace?.name || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/workspaces/${workspace.id}`);
  };

  return (
    <AppShell title={`Edit Workspace: ${workspace?.name}`}>
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Workspace: ${workspace?.name}`}
          description="Rename this workspace partition."
          actions={
            <Link href="/workspaces">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Workspaces
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <Input
              label="Workspace Name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              error={errors.name}
              required
            />

            <div className="pt-4 flex items-center justify-end gap-2 border-t border-[var(--border-subtle)]">
              <Link href="/workspaces">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Update Workspace
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
