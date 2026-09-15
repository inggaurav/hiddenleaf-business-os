import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
  ArrowLeft,
  Plus,
  Edit2,
  Trash2,
  FolderTree,
  CheckCircle,
  XCircle,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { Card } from '@/Components/UI/Card';
import { Modal } from '@/Components/UI/Modal';
import { SuggestionCategoryItem } from './types';

interface CategoriesProps {
  categories: SuggestionCategoryItem[];
}

export default function Categories({ categories }: CategoriesProps) {
  const [modalOpen, setModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState<SuggestionCategoryItem | null>(null);

  const form = useForm({
    name: '',
    color: '#FF6B6B',
    description: '',
    is_active: true,
    display_order: 0,
  });

  const openCreateModal = () => {
    setEditingCategory(null);
    form.setData({
      name: '',
      color: '#FF6B6B',
      description: '',
      is_active: true,
      display_order: 0,
    });
    setModalOpen(true);
  };

  const openEditModal = (cat: SuggestionCategoryItem) => {
    setEditingCategory(cat);
    form.setData({
      name: cat.name,
      color: cat.color,
      description: cat.description || '',
      is_active: Boolean(cat.is_active),
      display_order: cat.display_order ?? 0,
    });
    setModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingCategory) {
      form.put(`/suggestion-box/categories/${editingCategory.id}`, {
        onSuccess: () => {
          setModalOpen(false);
          form.reset();
        },
      });
    } else {
      form.post('/suggestion-box/categories', {
        onSuccess: () => {
          setModalOpen(false);
          form.reset();
        },
      });
    }
  };

  const handleDelete = (cat: SuggestionCategoryItem) => {
    if (confirm(`Delete category "${cat.name}"?`)) {
      router.delete(`/suggestion-box/categories/${cat.id}`);
    }
  };

  return (
    <AppShell title="Suggestion Categories">
      <Head title="Suggestion Categories" />

      <div className="space-y-6 pb-12 max-w-4xl mx-auto">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Link href="/suggestion-box/admin">
              <Button variant="outline" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Admin Review
              </Button>
            </Link>
            <div>
              <h1 className="text-xl font-semibold text-[var(--text-primary)]">
                Suggestion Categories
              </h1>
              <p className="text-sm text-[var(--text-secondary)] mt-0.5">
                Organize employee ideas and feedback into strategic categories.
              </p>
            </div>
          </div>

          <Button variant="neutral" onClick={openCreateModal} icon={<Plus className="w-4 h-4" />}>
            New Category
          </Button>
        </div>

        {/* Categories List */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {categories.map((cat) => (
            <Card key={cat.id} level={1} className="flex flex-col justify-between space-y-4">
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span
                      className="w-3.5 h-3.5 rounded-full flex-shrink-0"
                      style={{ backgroundColor: cat.color }}
                    />
                    <h3 className="text-sm font-semibold text-[var(--text-primary)]">{cat.name}</h3>
                  </div>
                  {cat.is_active ? (
                    <Badge variant="emerald" size="sm">Active</Badge>
                  ) : (
                    <Badge variant="neutral" size="sm">Inactive</Badge>
                  )}
                </div>

                {cat.description && (
                  <p className="text-xs text-[var(--text-secondary)] leading-relaxed">
                    {cat.description}
                  </p>
                )}
              </div>

              <div className="pt-3 border-t border-[var(--border-subtle)] flex items-center justify-between">
                <span className="text-xs text-[var(--text-tertiary)]">
                  {cat.suggestions_count ?? 0} suggestions
                </span>

                <div className="flex items-center gap-1.5">
                  <button
                    onClick={() => openEditModal(cat)}
                    className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface-3)] transition-colors"
                  >
                    <Edit2 className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => handleDelete(cat)}
                    className="p-1.5 rounded-lg text-rose-400/80 hover:text-rose-300 hover:bg-[var(--surface-3)] transition-colors"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      </div>

      {/* Create / Edit Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingCategory ? 'Edit Category' : 'Create Category'}
        maxWidth="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4 pt-2">
          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Name <span className="text-rose-400">*</span>
            </label>
            <input
              type="text"
              required
              value={form.data.name}
              onChange={(e) => form.setData('name', e.target.value)}
              placeholder="e.g., Workplace Culture, Product Feature"
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                Color Tag
              </label>
              <div className="flex items-center gap-2">
                <input
                  type="color"
                  value={form.data.color}
                  onChange={(e) => form.setData('color', e.target.value)}
                  className="w-9 h-9 p-0.5 rounded-lg bg-[var(--surface-2)] border border-[var(--border-medium)] cursor-pointer"
                />
                <input
                  type="text"
                  value={form.data.color}
                  onChange={(e) => form.setData('color', e.target.value)}
                  className="flex-1 px-3 py-2 rounded-xl text-xs bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                Display Order
              </label>
              <input
                type="number"
                value={form.data.display_order}
                onChange={(e) => form.setData('display_order', parseInt(e.target.value) || 0)}
                className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
              Description
            </label>
            <textarea
              rows={3}
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              placeholder="Brief summary of what falls under this category..."
              className="w-full px-3 py-2 rounded-xl text-sm bg-[var(--surface-2)] border border-[var(--border-medium)] text-[var(--text-primary)] focus:outline-none"
            />
          </div>

          <div className="pt-2">
            <label className="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
              <input
                type="checkbox"
                checked={form.data.is_active}
                onChange={(e) => form.setData('is_active', e.target.checked)}
                className="rounded border-[var(--border-medium)] bg-[var(--surface-2)]"
              />
              <span>Category is active for new submissions</span>
            </label>
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[var(--border-subtle)]">
            <Button
              type="button"
              variant="outline"
              onClick={() => setModalOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="neutral"
              loading={form.processing}
            >
              {editingCategory ? 'Update Category' : 'Create Category'}
            </Button>
          </div>
        </form>
      </Modal>
    </AppShell>
  );
}
