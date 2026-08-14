import React, { useState } from 'react';
import { usePage, useForm, Link, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Modal } from '@/Components/UI/Modal';
import { Input } from '@/Components/UI/Input';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { FolderTree, Plus, Trash2, ArrowLeft } from 'lucide-react';

export default function HelpdeskCategoriesIndex() {
  const { categories = [] } = usePage<any>().props;
  const [modalOpen, setModalOpen] = useState(false);

  const { data, setData, post, processing, reset, errors } = useForm({
    name: '',
    color: '#8b5cf6',
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    post('/helpdesk-categories', {
      onSuccess: () => {
        setModalOpen(false);
        reset();
      },
    });
  };

  const handleDelete = (id: number) => {
    if (confirm('Delete this ticket category?')) {
      router.delete(`/helpdesk-categories/${id}`);
    }
  };

  const columns: Column<any>[] = [
    {
      key: 'name',
      header: 'Category Name',
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <span
            className="w-3 h-3 rounded-full flex-shrink-0"
            style={{ backgroundColor: row.color || '#8b5cf6' }}
          />
          <span className="font-semibold text-white">{row.name}</span>
        </div>
      ),
    },
    {
      key: 'color',
      header: 'Color Accent',
      render: (row) => <span className="font-mono text-xs text-gray-400">{row.color || '#8b5cf6'}</span>,
    },
    {
      key: 'actions',
      header: 'Actions',
      className: 'text-right',
      render: (row) => (
        <Button
          variant="ghost"
          size="sm"
          className="text-rose-400 hover:text-rose-300"
          onClick={() => handleDelete(row.id)}
        >
          <Trash2 className="w-4 h-4" />
        </Button>
      ),
    },
  ];

  return (
    <AppShell title="Ticket Categories">
      <div className="space-y-6">
        <SectionHeader
          title="Support Ticket Categories"
          description="Group customer issues into logical support queues with custom color badges."
          badge={<Badge variant="purple" size="sm">Taxonomy</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/helpdesk-tickets">
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back to Tickets
                </Button>
              </Link>
              <Button
                variant="primary"
                size="sm"
                icon={<Plus className="w-4 h-4" />}
                onClick={() => setModalOpen(true)}
              >
                New Category
              </Button>
            </div>
          }
        />

        <DataTable
          columns={columns}
          data={categories}
          searchPlaceholder="Search categories..."
          searchKeys={['name']}
          emptyTitle="No categories created"
          emptyDescription="Create categories like Billing, Technical, or Onboarding."
        />

        <Modal
          isOpen={modalOpen}
          onClose={() => setModalOpen(false)}
          title="Create Ticket Category"
          description="Define category title and badge color accent."
        >
          <form onSubmit={handleCreate} className="space-y-4">
            <Input
              label="Category Name"
              placeholder="e.g. Billing & Payment Queries"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              error={errors.name}
              required
            />
            <div>
              <label className="block text-xs font-medium text-gray-300 mb-1.5">Color Tag</label>
              <input
                type="color"
                value={data.color}
                onChange={(e) => setData('color', e.target.value)}
                className="w-12 h-8 rounded border border-white/20 bg-transparent cursor-pointer"
              />
            </div>

            <div className="pt-4 flex items-center justify-end gap-2">
              <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" variant="primary" loading={processing}>
                Save Category
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    </AppShell>
  );
}
