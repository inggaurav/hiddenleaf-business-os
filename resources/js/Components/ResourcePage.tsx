import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from './UI/Card';
import { Button } from './UI/Button';
import { Input } from './UI/Input';
import { Select } from './UI/Select';
import { Textarea } from './UI/Textarea';
import { Checkbox } from './UI/Checkbox';
import { Badge, StatusBadge } from './UI/Badge';
import { SectionHeader } from './UI/SectionHeader';
import { DataTable, Column } from './UI/DataTable';
import { AlertDialog } from './UI/AlertDialog';
import { Plus, Search, Trash2, Edit, Eye, ArrowLeft, Save } from 'lucide-react';

type Entity = Record<string, any>;

interface IndexConfig {
  title: string;
  collectionKey: string;
  resourcePath?: string;
  createPath?: string;
  columns?: string[];
}

interface FieldConfig {
  name: string;
  label: string;
  type?: 'text' | 'email' | 'password' | 'number' | 'date' | 'textarea' | 'select' | 'checkbox';
  required?: boolean;
  optionsProp?: string;
  optionLabel?: string;
  optionValue?: string;
}

interface FormConfig {
  title: string;
  submitPath: string | ((record: Entity) => string);
  backPath: string;
  method?: 'post' | 'put';
  recordKey?: string;
  fields: FieldConfig[];
  lineItems?: boolean;
}

interface ShowConfig {
  title: string;
  recordKey: string;
  backPath: string;
  editPath?: (record: Entity) => string;
}

function recordsFrom(value: any): Entity[] {
  if (Array.isArray(value)) return value;
  return Array.isArray(value?.data) ? value.data : [];
}

function humanize(value: string): string {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function displayValue(value: any): React.ReactNode {
  if (value === null || value === undefined || value === '') return <span className="text-gray-500">—</span>;
  if (typeof value === 'boolean') return <Badge variant={value ? 'success' : 'neutral'} size="sm">{value ? 'Yes' : 'No'}</Badge>;
  if (Array.isArray(value)) return value.map((item) => typeof item === 'object' ? item.name ?? item.id : item).join(', ');
  if (typeof value === 'object') return value.name ?? value.title ?? value.label ?? JSON.stringify(value);
  return String(value);
}

export function createIndexPage(config: IndexConfig) {
  return function ResourceIndexPage() {
    const props = usePage<Entity>().props;
    const collection = props[config.collectionKey];
    const records = recordsFrom(collection);
    const [deleteTarget, setDeleteTarget] = useState<Entity | null>(null);

    const columnKeys = config.columns ?? Object.keys(records[0] ?? {}).filter((key) => !['created_at', 'updated_at', 'deleted_at'].includes(key)).slice(0, 5);

    const tableColumns: Column<Entity>[] = columnKeys.map((colKey) => ({
      key: colKey,
      header: humanize(colKey),
      sortable: true,
      render: (row) => (
        <span className="text-xs text-gray-200">{displayValue(row[colKey])}</span>
      ),
    }));

    if (config.resourcePath) {
      tableColumns.push({
        key: 'actions',
        header: 'Actions',
        className: 'text-right',
        render: (row) => (
          <div className="flex items-center justify-end gap-1.5">
            <Link href={`${config.resourcePath}/${row.id}`}>
              <Button variant="ghost" size="sm" icon={<Eye className="w-3.5 h-3.5" />}>
                View
              </Button>
            </Link>
            <Link href={`${config.resourcePath}/${row.id}/edit`}>
              <Button variant="ghost" size="sm" icon={<Edit className="w-3.5 h-3.5" />}>
                Edit
              </Button>
            </Link>
            <Button
              variant="ghost"
              size="sm"
              className="text-rose-400 hover:text-rose-300"
              icon={<Trash2 className="w-3.5 h-3.5" />}
              onClick={() => setDeleteTarget(row)}
            >
              Delete
            </Button>
          </div>
        ),
      });
    }

    return (
      <AppShell title={config.title}>
        <div className="space-y-6">
          <SectionHeader
            title={config.title}
            description={`Manage workspace-scoped ${config.title.toLowerCase()} records.`}
            actions={
              (config.createPath || config.resourcePath) && (
                <Link href={config.createPath ?? `${config.resourcePath}/create`}>
                  <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
                    Create {config.title.replace(/s$/, '')}
                  </Button>
                </Link>
              )
            }
          />

          <DataTable
            columns={tableColumns}
            data={collection ?? []}
            searchPlaceholder={`Search ${config.title.toLowerCase()}...`}
            emptyTitle={`No ${config.title.toLowerCase()} found`}
            emptyDescription={`Create your first ${config.title.toLowerCase()} record.`}
          />

          {/* Accessible Destructive Confirmation Dialog */}
          <AlertDialog
            isOpen={Boolean(deleteTarget)}
            onClose={() => setDeleteTarget(null)}
            title={`Delete ${config.title.replace(/s$/, '')}`}
            description="Are you sure you want to delete this record? This action is unalterable."
            entityName={deleteTarget?.name || deleteTarget?.title || (deleteTarget?.id ? `ID #${deleteTarget.id}` : undefined)}
            onConfirm={() => {
              if (deleteTarget && config.resourcePath) {
                router.delete(`${config.resourcePath}/${deleteTarget.id}`);
              }
            }}
          />
        </div>
      </AppShell>
    );
  };
}

export function createFormPage(config: FormConfig) {
  return function ResourceFormPage() {
    const props = usePage<Entity>().props;
    const record: Entity = config.recordKey ? props[config.recordKey] ?? {} : {};
    const initial: Entity = Object.fromEntries(config.fields.map((field) => [field.name, record[field.name] ?? (field.type === 'checkbox' ? false : '')]));
    if (config.lineItems) initial.items = record.items?.length ? record.items : [{ item_name: '', quantity: 1, price: 0 }];
    
    const { data, setData, post, put, processing, errors } = useForm<Entity>(initial);
    const submitPath = typeof config.submitPath === 'function' ? config.submitPath(record) : config.submitPath;

    const submit = (event: React.FormEvent) => { 
      event.preventDefault(); 
      config.method === 'put' ? put(submitPath) : post(submitPath); 
    };

    const setItem = (index: number, name: string, value: string | number) => 
      setData('items', data.items.map((item: Entity, itemIndex: number) => itemIndex === index ? { ...item, [name]: value } : item));

    return (
      <AppShell title={config.title}>
        <div className="max-w-4xl mx-auto space-y-6">
          <SectionHeader
            title={config.title}
            description="Complete the required attributes and line records."
            actions={
              <Link href={config.backPath}>
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back
                </Button>
              </Link>
            }
          />

          <form onSubmit={submit} className="space-y-6">
            <Card level={0} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {config.fields.map((field) => {
                  if (field.type === 'textarea') {
                    return (
                      <div key={field.name} className="md:col-span-2">
                        <Textarea
                          label={field.label}
                          value={data[field.name] ?? ''}
                          onChange={(e) => setData(field.name, e.target.value)}
                          error={errors[field.name]}
                          required={field.required}
                          rows={4}
                        />
                      </div>
                    );
                  }

                  if (field.type === 'select') {
                    return (
                      <Select
                        key={field.name}
                        label={field.label}
                        value={data[field.name] ?? ''}
                        onChange={(e) => setData(field.name, e.target.value)}
                        error={errors[field.name]}
                        required={field.required}
                      >
                        <option value="">Select {field.label}</option>
                        {recordsFrom(props[field.optionsProp ?? '']).map((opt) => (
                          <option key={opt[field.optionValue ?? 'id']} value={opt[field.optionValue ?? 'id']}>
                            {opt[field.optionLabel ?? 'name']}
                          </option>
                        ))}
                      </Select>
                    );
                  }

                  if (field.type === 'checkbox') {
                    return (
                      <div key={field.name} className="pt-2 md:col-span-2">
                        <Checkbox
                          label={field.label}
                          checked={Boolean(data[field.name])}
                          onChange={(e) => setData(field.name, e.target.checked)}
                        />
                      </div>
                    );
                  }

                  return (
                    <Input
                      key={field.name}
                      label={field.label}
                      type={field.type ?? 'text'}
                      value={data[field.name] ?? ''}
                      onChange={(e) => setData(field.name, e.target.value)}
                      error={errors[field.name]}
                      required={field.required}
                    />
                  );
                })}
              </div>

              {config.lineItems && (
                <div className="pt-4 border-t border-white/10 space-y-3">
                  <div className="flex items-center justify-between">
                    <h4 className="text-xs font-bold text-white uppercase tracking-wider">Line Items</h4>
                    <Button
                      type="button"
                      variant="secondary"
                      size="sm"
                      onClick={() => setData('items', [...data.items, { item_name: '', quantity: 1, price: 0 }])}
                    >
                      Add Item
                    </Button>
                  </div>

                  <div className="space-y-2">
                    {data.items.map((item: Entity, index: number) => (
                      <div key={index} className="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center p-2 rounded-lg bg-black/30 border border-white/5">
                        <div className="sm:col-span-6">
                          <input
                            placeholder="Item description"
                            value={item.item_name}
                            onChange={(e) => setItem(index, 'item_name', e.target.value)}
                            className="w-full bg-[#12161E] rounded-lg border border-white/10 px-3 py-1.5 text-xs text-white"
                            required
                          />
                        </div>
                        <div className="sm:col-span-2">
                          <input
                            type="number"
                            min="1"
                            value={item.quantity}
                            onChange={(e) => setItem(index, 'quantity', e.target.value)}
                            className="w-full bg-[#12161E] rounded-lg border border-white/10 px-3 py-1.5 text-xs text-white"
                            required
                          />
                        </div>
                        <div className="sm:col-span-3">
                          <input
                            type="number"
                            step="0.01"
                            value={item.price}
                            onChange={(e) => setItem(index, 'price', e.target.value)}
                            className="w-full bg-[#12161E] rounded-lg border border-white/10 px-3 py-1.5 text-xs text-white"
                            required
                          />
                        </div>
                        <div className="sm:col-span-1 text-right">
                          <button
                            type="button"
                            disabled={data.items.length === 1}
                            onClick={() => setData('items', data.items.filter((_: Entity, itemIndex: number) => itemIndex !== index))}
                            className="text-rose-400 hover:text-rose-300 disabled:opacity-20 cursor-pointer p-1"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div className="pt-4 border-t border-white/10 flex items-center justify-between">
                <Link href={config.backPath}>
                  <Button variant="ghost">Cancel</Button>
                </Link>
                <Button
                  type="submit"
                  variant="primary"
                  loading={processing}
                  icon={<Save className="w-4 h-4" />}
                >
                  Save Record
                </Button>
              </div>
            </Card>
          </form>
        </div>
      </AppShell>
    );
  };
}

export function createShowPage(config: ShowConfig) {
  return function ResourceShowPage() {
    const record: Entity = usePage<Entity>().props[config.recordKey] ?? {};
    const entries = Object.entries(record).filter(([key]) => !['created_at', 'updated_at', 'deleted_at'].includes(key));

    return (
      <AppShell title={config.title}>
        <div className="max-w-4xl mx-auto space-y-6">
          <SectionHeader
            title={config.title}
            description="Record summary and relationship attributes."
            actions={
              <div className="flex items-center gap-2">
                <Link href={config.backPath}>
                  <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                    Back
                  </Button>
                </Link>
                {config.editPath && (
                  <Link href={config.editPath(record)}>
                    <Button variant="primary" size="sm" icon={<Edit className="w-4 h-4" />}>
                      Edit Record
                    </Button>
                  </Link>
                )}
              </div>
            }
          />

          <Card level={0} className="p-6">
            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {entries.map(([key, value]) => (
                <div key={key} className="p-3 rounded-lg bg-white/[0.02] border border-white/5 space-y-1">
                  <dt className="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                    {humanize(key)}
                  </dt>
                  <dd className="text-xs font-semibold text-white break-words">
                    {displayValue(value)}
                  </dd>
                </div>
              ))}
            </dl>
          </Card>
        </div>
      </AppShell>
    );
  };
}
