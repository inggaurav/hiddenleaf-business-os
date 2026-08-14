import React, { useState } from 'react';
import { usePage, useForm, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Modal } from '@/Components/UI/Modal';
import { Input } from '@/Components/UI/Input';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Globe, Plus, Edit, Trash2 } from 'lucide-react';

export default function LanguagesIndex() {
  const { languages = [], currentLang } = usePage<any>().props;
  const [modalOpen, setModalOpen] = useState(false);

  const { data, setData, post, processing, reset, errors } = useForm({
    code: '',
    name: '',
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    post('/languages', {
      onSuccess: () => {
        setModalOpen(false);
        reset();
      },
    });
  };

  const handleSwitch = (code: string) => {
    router.post(`/languages/switch/${code}`);
  };

  const columns: Column<any>[] = [
    {
      key: 'code',
      header: 'Language Code',
      sortable: true,
      render: (row) => (
        <span className="font-mono text-xs font-bold text-violet-300 uppercase px-2 py-1 rounded bg-violet-600/10 border border-violet-500/20">
          {row.code}
        </span>
      ),
    },
    {
      key: 'name',
      header: 'Language Name',
      sortable: true,
      render: (row) => <span className="font-semibold text-white">{row.name}</span>,
    },
    {
      key: 'is_active',
      header: 'Active State',
      sortable: true,
      render: (row) => (
        <Badge variant={currentLang === row.code ? 'success' : 'neutral'} size="sm" dot>
          {currentLang === row.code ? 'Current Active' : 'Available'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-2">
          {currentLang !== row.code && (
            <Button
              variant="secondary"
              size="sm"
              onClick={() => handleSwitch(row.code)}
            >
              Switch To
            </Button>
          )}
          <Link href={`/languages/${row.code}`}>
            <Button variant="ghost" size="sm" icon={<Edit className="w-3.5 h-3.5" />}>
              Translations
            </Button>
          </Link>
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Languages & Localization">
      <div className="space-y-6">
        <SectionHeader
          title="Internationalization & Languages"
          description="Manage translation dictionaries, add global locales, and switch user interface languages."
          badge={<Badge variant="purple" size="sm">i18n Engine</Badge>}
          actions={
            <Button
              variant="primary"
              size="sm"
              icon={<Plus className="w-4 h-4" />}
              onClick={() => setModalOpen(true)}
            >
              Add Language
            </Button>
          }
        />

        <DataTable
          columns={columns}
          data={languages}
          searchPlaceholder="Search languages..."
          searchKeys={['name', 'code']}
          emptyTitle="No languages registered"
          emptyDescription="Add a new locale dictionary."
        />

        <Modal
          isOpen={modalOpen}
          onClose={() => setModalOpen(false)}
          title="Add New Language"
          description="Register a standard ISO locale code (e.g. de, fr, es, ja)."
        >
          <form onSubmit={handleCreate} className="space-y-4">
            <Input
              label="ISO Language Code"
              placeholder="e.g. fr"
              value={data.code}
              onChange={(e) => setData('code', e.target.value.toLowerCase())}
              error={errors.code}
              required
            />
            <Input
              label="Language Full Name"
              placeholder="e.g. Français"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              error={errors.name}
              required
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" variant="primary" loading={processing}>
                Save Language
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    </AppShell>
  );
}
