import React from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Save, Settings2, ShieldCheck } from 'lucide-react';

type FieldType = 'text' | 'password' | 'email' | 'number' | 'url' | 'textarea' | 'select' | 'toggle' | 'file' | 'color';

interface SettingsField {
  key: string;
  label: string;
  type: FieldType;
  options?: Record<string, string>;
}

interface SettingsSection {
  id: string;
  label: string;
  scope: 'platform' | 'organization' | 'workspace' | 'user';
  permission: string;
  order: number;
  module?: string | null;
  replacement?: string | null;
  source: string;
  fields: SettingsField[];
}

function SectionForm({ section, resolved }: { section: SettingsSection; resolved: Record<string, any> }) {
  const initialValues = section.fields.reduce<Record<string, any>>((values, field) => {
    values[field.key] = resolved[field.key] ?? (field.type === 'toggle' ? 'off' : '');
    return values;
  }, {});

  const { data, setData, post, processing, errors } = useForm<{
    _section: string;
    values: Record<string, any>;
  }>({
    _section: section.id,
    values: initialValues,
  });

  const update = (key: string, value: any) => setData('values', { ...data.values, [key]: value });

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    post('/settings', { forceFormData: true, preserveScroll: true });
  };

  const renderField = (field: SettingsField) => {
    const error = (errors as Record<string, string>)[`values.${field.key}`];

    if (field.type === 'textarea') {
      return (
        <label key={field.key} className="space-y-2 text-sm text-[var(--text-secondary)]">
          <span>{field.label}</span>
          <textarea
            className="min-h-28 w-full rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-3 py-2 text-[var(--text-primary)]"
            value={data.values[field.key] ?? ''}
            onChange={(event) => update(field.key, event.target.value)}
          />
          {error && <span className="text-xs text-red-400">{error}</span>}
        </label>
      );
    }

    if (field.type === 'select' || field.type === 'toggle') {
      const options = field.type === 'toggle' ? { on: 'Enabled', off: 'Disabled' } : (field.options || {});
      return (
        <Select
          key={field.key}
          label={field.label}
          value={String(data.values[field.key] ?? '')}
          onChange={(event) => update(field.key, event.target.value)}
          error={error}
        >
          {Object.entries(options).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </Select>
      );
    }

    if (field.type === 'file') {
      return (
        <label key={field.key} className="space-y-2 text-sm text-[var(--text-secondary)]">
          <span>{field.label}</span>
          <input
            type="file"
            accept=".png,.jpg,.jpeg,.webp,.svg,.ico"
            className="block w-full rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-3 py-2 text-sm"
            onChange={(event) => update(field.key, event.target.files?.[0] || null)}
          />
          {typeof resolved[field.key] === 'string' && resolved[field.key] && <span className="block truncate text-xs text-[var(--text-muted)]">Current: {resolved[field.key]}</span>}
          {error && <span className="text-xs text-red-400">{error}</span>}
        </label>
      );
    }

    return (
      <Input
        key={field.key}
        label={field.label}
        type={field.type === 'color' ? 'color' : field.type}
        value={data.values[field.key] ?? ''}
        onChange={(event) => update(field.key, event.target.value)}
        error={error}
      />
    );
  };

  return (
    <form onSubmit={submit} id={section.id}>
      <Card level={0} className="space-y-5">
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-[var(--border-subtle)] pb-4">
          <div>
            <div className="flex items-center gap-2">
              <Settings2 className="h-4 w-4 text-purple-400" />
              <h2 className="font-semibold text-[var(--text-primary)]">{section.label}</h2>
            </div>
            <div className="mt-2 flex flex-wrap gap-2">
              <Badge variant="neutral" size="sm">{section.scope}</Badge>
              <Badge variant="purple" size="sm">{section.permission}</Badge>
              {section.module && <Badge variant="neutral" size="sm">Module: {section.module}</Badge>}
            </div>
          </div>
          <Button type="submit" loading={processing} icon={<Save className="h-4 w-4" />}>Save section</Button>
          {section.id === 'platform.cache' && <Button type="button" variant="outline" onClick={() => router.post('/settings/cache-clear')}>Clear application cache</Button>}
          {section.id === 'platform.mail' && <Button type="button" variant="outline" onClick={() => router.post('/settings/test-mail', { email: String(data.values.fromAddress || '') })}>Send test mail</Button>}
        </div>

        {section.replacement && (
          <div className="rounded-xl border border-sky-500/20 bg-sky-500/10 p-3 text-sm text-sky-200">{section.replacement}</div>
        )}

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {section.fields.map(renderField)}
        </div>
      </Card>
    </form>
  );
}

export default function SettingsIndex() {
  const { resolvedSettings = {}, settingsSections = [], isSuperAdmin = false, settingsLinks = {} } = usePage<any>().props as {
    resolvedSettings: Record<string, any>;
    settingsSections: SettingsSection[];
    isSuperAdmin: boolean;
    settingsLinks: { templates?: boolean; webhooks?: boolean; api?: boolean };
  };

  return (
    <AppShell title={isSuperAdmin ? 'Platform Settings' : 'Company Settings'}>
      <div className="mx-auto max-w-6xl space-y-6">
        <SectionHeader
          title={isSuperAdmin ? 'Platform settings' : 'Company and workspace settings'}
          description="Only sections authorized for your role and active modules are shown. Each section saves to its declared scope."
          badge={<Badge variant="purple" size="sm">Registry driven</Badge>}
          actions={(
            <div className="flex flex-wrap gap-2">
              {settingsLinks.templates && <Link href="/settings/email-templates"><Button variant="outline" size="sm">Email templates</Button></Link>}
              {settingsLinks.templates && <Link href="/notification-templates"><Button variant="outline" size="sm">Notifications</Button></Link>}
              {settingsLinks.webhooks && <Link href="/webhooks"><Button variant="outline" size="sm">Webhooks</Button></Link>}
              {settingsLinks.api && <Link href="/settings/api-tokens"><Button variant="outline" size="sm">API tokens</Button></Link>}
            </div>
          )}
        />

        {settingsSections.length === 0 ? (
          <Card level={0} className="flex items-center gap-3 p-6 text-[var(--text-secondary)]">
            <ShieldCheck className="h-5 w-5 text-emerald-400" />
            No administrative settings sections are assigned to this role.
          </Card>
        ) : settingsSections.map((section) => (
          <SectionForm key={section.id} section={section} resolved={resolvedSettings} />
        ))}
      </div>
    </AppShell>
  );
}
