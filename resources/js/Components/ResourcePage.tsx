import React from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Plus, Search, Trash2 } from 'lucide-react';

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
  if (value === null || value === undefined || value === '') return <span className="text-slate-500">—</span>;
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';
  if (Array.isArray(value)) return value.map((item) => typeof item === 'object' ? item.name ?? item.id : item).join(', ');
  if (typeof value === 'object') return value.name ?? value.title ?? value.label ?? JSON.stringify(value);
  return String(value);
}

export function createIndexPage(config: IndexConfig) {
  return function ResourceIndexPage() {
    const props = usePage<Entity>().props;
    const collection = props[config.collectionKey];
    const records = recordsFrom(collection);
    const query = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search).get('search') ?? '';
    const columns = config.columns ?? Object.keys(records[0] ?? {}).filter((key) => !['created_at', 'updated_at', 'deleted_at'].includes(key)).slice(0, 6);

    const search = (event: React.FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      router.get(typeof window === 'undefined' ? '' : window.location.pathname, { search: data.get('search') }, { preserveState: true, replace: true });
    };

    return (
      <AppShell>
        <Head title={config.title} />
        <div className="space-y-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 className="text-2xl font-bold text-slate-900">{config.title}</h1><p className="mt-1 text-sm text-slate-500">Manage workspace-scoped {config.title.toLowerCase()}.</p></div>
            {(config.createPath || config.resourcePath) && <Link href={config.createPath ?? `${config.resourcePath}/create`} className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><Plus size={16} />Create</Link>}
          </div>
          <form onSubmit={search} className="flex max-w-lg gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <Search size={18} className="mt-2 text-slate-400" /><input name="search" defaultValue={query} aria-label={`Search ${config.title}`} placeholder={`Search ${config.title.toLowerCase()}`} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" /><button className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Search</button>
          </form>
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="overflow-x-auto"><table className="min-w-full divide-y divide-slate-200 text-sm"><thead className="bg-slate-50"><tr>{columns.map((column) => <th key={column} className="px-4 py-3 text-left font-semibold text-slate-600">{humanize(column)}</th>)}{config.resourcePath && <th className="px-4 py-3 text-right font-semibold text-slate-600">Actions</th>}</tr></thead><tbody className="divide-y divide-slate-100">{records.length ? records.map((record, index) => <tr key={record.id ?? index} className="hover:bg-slate-50">{columns.map((column) => <td key={column} className="max-w-xs truncate px-4 py-3 text-slate-700">{displayValue(record[column])}</td>)}{config.resourcePath && <td className="whitespace-nowrap px-4 py-3 text-right"><Link href={`${config.resourcePath}/${record.id}`} className="mr-3 font-medium text-emerald-700">View</Link><Link href={`${config.resourcePath}/${record.id}/edit`} className="mr-3 font-medium text-slate-700">Edit</Link><button aria-label={`Delete ${record.name ?? record.id}`} onClick={() => confirm('Delete this record?') && router.delete(`${config.resourcePath}/${record.id}`)} className="text-rose-600"><Trash2 size={16} /></button></td>}</tr>) : <tr><td colSpan={columns.length + (config.resourcePath ? 1 : 0)} className="px-4 py-12 text-center text-slate-500">No records found.</td></tr>}</tbody></table></div>
            {Array.isArray(collection?.links) && <nav className="flex flex-wrap gap-2 border-t border-slate-200 px-4 py-3" aria-label="Pagination">{collection.links.map((link: Entity, index: number) => link.url ? <Link key={index} href={link.url} preserveScroll className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} className="rounded px-3 py-1 text-sm text-slate-400" dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav>}
          </div>
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
    const submit = (event: React.FormEvent) => { event.preventDefault(); config.method === 'put' ? put(submitPath) : post(submitPath); };
    const setItem = (index: number, name: string, value: string | number) => setData('items', data.items.map((item: Entity, itemIndex: number) => itemIndex === index ? { ...item, [name]: value } : item));

    return <AppShell><Head title={config.title} /><div className="mx-auto max-w-4xl"><div className="mb-6"><h1 className="text-2xl font-bold text-slate-900">{config.title}</h1><p className="mt-1 text-sm text-slate-500">Fields marked required must be completed.</p></div><form onSubmit={submit} className="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><div className="grid gap-5 md:grid-cols-2">{config.fields.map((field) => <label key={field.name} className={field.type === 'textarea' ? 'md:col-span-2' : ''}><span className="mb-1.5 block text-sm font-medium text-slate-700">{field.label}{field.required && ' *'}</span>{field.type === 'textarea' ? <textarea value={data[field.name] ?? ''} onChange={(event) => setData(field.name, event.target.value)} required={field.required} rows={4} className="w-full rounded-lg border border-slate-300 px-3 py-2" /> : field.type === 'select' ? <select value={data[field.name] ?? ''} onChange={(event) => setData(field.name, event.target.value)} required={field.required} className="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Select {field.label}</option>{recordsFrom(props[field.optionsProp ?? '']).map((option) => <option key={option[field.optionValue ?? 'id']} value={option[field.optionValue ?? 'id']}>{option[field.optionLabel ?? 'name']}</option>)}</select> : field.type === 'checkbox' ? <input type="checkbox" checked={Boolean(data[field.name])} onChange={(event) => setData(field.name, event.target.checked)} className="h-5 w-5 rounded border-slate-300 text-emerald-600" /> : <input type={field.type ?? 'text'} value={data[field.name] ?? ''} onChange={(event) => setData(field.name, event.target.value)} required={field.required} className="w-full rounded-lg border border-slate-300 px-3 py-2" />}{errors[field.name] && <span className="mt-1 block text-xs text-rose-600">{String(errors[field.name])}</span>}</label>)}</div>{config.lineItems && <section className="border-t border-slate-200 pt-5"><div className="mb-3 flex items-center justify-between"><h2 className="font-semibold text-slate-900">Line items</h2><button type="button" onClick={() => setData('items', [...data.items, { item_name: '', quantity: 1, price: 0 }])} className="text-sm font-medium text-emerald-700">Add item</button></div><div className="space-y-3">{data.items.map((item: Entity, index: number) => <div key={index} className="grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-[1fr_8rem_10rem_auto]"><input aria-label="Item name" placeholder="Item name" value={item.item_name} onChange={(event) => setItem(index, 'item_name', event.target.value)} required className="rounded-lg border border-slate-300 px-3 py-2" /><input aria-label="Quantity" type="number" min="1" step="0.01" value={item.quantity} onChange={(event) => setItem(index, 'quantity', event.target.value)} required className="rounded-lg border border-slate-300 px-3 py-2" /><input aria-label="Price" type="number" min="0" step="0.01" value={item.price} onChange={(event) => setItem(index, 'price', event.target.value)} required className="rounded-lg border border-slate-300 px-3 py-2" /><button type="button" disabled={data.items.length === 1} onClick={() => setData('items', data.items.filter((_: Entity, itemIndex: number) => itemIndex !== index))} className="px-2 text-rose-600 disabled:opacity-30"><Trash2 size={17} /></button></div>)}</div></section>}<div className="flex items-center justify-between border-t border-slate-200 pt-5"><Link href={config.backPath} className="text-sm font-medium text-slate-600">Cancel</Link><button disabled={processing} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Saving…' : 'Save'}</button></div></form></div></AppShell>;
  };
}

export function createShowPage(config: ShowConfig) {
  return function ResourceShowPage() {
    const record: Entity = usePage<Entity>().props[config.recordKey] ?? {};
    const entries = Object.entries(record).filter(([key]) => !['created_at', 'updated_at', 'deleted_at'].includes(key));
    return <AppShell><Head title={config.title} /><div className="mx-auto max-w-4xl space-y-6"><div className="flex items-center justify-between"><div><h1 className="text-2xl font-bold text-slate-900">{config.title}</h1><p className="mt-1 text-sm text-slate-500">Record details and related line items.</p></div>{config.editPath && <Link href={config.editPath(record)} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Edit</Link>}</div><dl className="grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-2">{entries.map(([key, value]) => <div key={key} className="bg-white p-4"><dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{humanize(key)}</dt><dd className="mt-1 text-sm text-slate-900">{displayValue(value)}</dd></div>)}</dl><Link href={config.backPath} className="inline-flex text-sm font-medium text-emerald-700">← Back to list</Link></div></AppShell>;
  };
}
