import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Globe2, Plus, Search, Save } from 'lucide-react';

export default function TranslationsIndex({ translations = {}, locales = [] }: any) {
  const [selectedLocale, setSelectedLocale] = useState('en');
  const [searchFilter, setSearchFilter] = useState('');

  const { data, setData, post, processing, reset } = useForm({
    locale: 'en',
    key: '',
    value: '',
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/super-admin/translations', {
      onSuccess: () => reset('key', 'value'),
    });
  };

  const currentStrings: Record<string, string> = translations[selectedLocale] || {};
  const filteredEntries = Object.entries(currentStrings).filter(([k, v]) =>
    k.toLowerCase().includes(searchFilter.toLowerCase()) || String(v).toLowerCase().includes(searchFilter.toLowerCase())
  );

  return (
    <AppShell title="Platform Translations" breadcrumbs={[{ label: 'Platform Administration' }, { label: 'Translations' }]}>
      <div className="space-y-6">
        <SectionHeader
          title="Platform Translations"
          description="Manage global dictionary translations and localization strings across all supported platform locales."
          badge={<Badge variant="purple" size="sm">System Locales</Badge>}
        />

        <Card level={0} className="space-y-4">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-[var(--border-subtle)] pb-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-violet-600/10 border border-violet-500/20 flex items-center justify-center">
                <Globe2 className="w-5 h-5 text-violet-400" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-[var(--text-primary)]">Active Locale Dictionary</h3>
                <p className="text-xs text-[var(--text-tertiary)]">Select target language to view or modify dictionary keys.</p>
              </div>
            </div>

            <div className="w-full sm:w-48">
              <Select
                value={selectedLocale}
                onChange={(e) => {
                  setSelectedLocale(e.target.value);
                  setData('locale', e.target.value);
                }}
              >
                {locales.map((loc: string) => (
                  <option key={loc} value={loc}>
                    {loc.toUpperCase()} ({loc})
                  </option>
                ))}
              </Select>
            </div>
          </div>

          <form onSubmit={submit} className="rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-2)] p-4 space-y-4">
            <div className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] flex items-center gap-1.5">
              <Plus className="w-3.5 h-3.5" />
              Add or Update Translation Key ({selectedLocale.toUpperCase()})
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input
                label="Translation Key"
                placeholder="e.g. Dashboard or welcome_message"
                value={data.key}
                onChange={(e) => setData('key', e.target.value)}
                required
              />
              <Input
                label="Translation Value"
                placeholder="Localized string text"
                value={data.value}
                onChange={(e) => setData('value', e.target.value)}
                required
              />
            </div>
            <div className="flex justify-end">
              <Button type="submit" variant="primary" size="sm" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Translation
              </Button>
            </div>
          </form>

          <div className="space-y-3 pt-2">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)]">
                Dictionary Strings ({filteredEntries.length})
              </h4>
              <div className="w-full sm:w-64">
                <Input
                  placeholder="Filter keys or values..."
                  leftIcon={<Search className="w-4 h-4 text-[var(--text-tertiary)]" />}
                  value={searchFilter}
                  onChange={(e) => setSearchFilter(e.target.value)}
                />
              </div>
            </div>

            <div className="overflow-x-auto rounded-xl border border-[var(--border-subtle)]">
              <table className="w-full text-xs">
                <thead>
                  <tr className="border-b border-[var(--border-subtle)] bg-[var(--surface-2)] text-left text-[var(--text-tertiary)]">
                    <th className="p-3 font-semibold">Key</th>
                    <th className="p-3 font-semibold">Value</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-subtle)]">
                  {filteredEntries.length > 0 ? (
                    filteredEntries.map(([k, v]) => (
                      <tr key={k} className="hover:bg-white/[0.02] transition-colors">
                        <td className="p-3 font-mono text-[var(--text-secondary)] select-all">{k}</td>
                        <td className="p-3 text-[var(--text-primary)] font-medium">{String(v)}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={2} className="p-8 text-center text-xs text-[var(--text-tertiary)]">
                        No translation entries found matching filter.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </Card>
      </div>
    </AppShell>
  );
}
