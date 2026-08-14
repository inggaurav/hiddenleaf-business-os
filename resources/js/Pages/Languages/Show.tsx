import React, { useState } from 'react';
import { usePage, useForm, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, Search, Globe } from 'lucide-react';

export default function LanguageShow() {
  const { lang, translations = {} } = usePage<any>().props;

  const [searchFilter, setSearchFilter] = useState('');
  const [strings, setStrings] = useState<Record<string, string>>(translations);

  const { data, setData, post, processing } = useForm({
    translations: strings,
  });

  const handleStringChange = (key: string, val: string) => {
    const updated = { ...strings, [key]: val };
    setStrings(updated);
    setData('translations', updated);
  };

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    post(`/languages/${lang}`);
  };

  const filteredKeys = Object.keys(strings).filter((k) =>
    k.toLowerCase().includes(searchFilter.toLowerCase()) || (strings[k] && strings[k].toLowerCase().includes(searchFilter.toLowerCase()))
  );

  return (
    <AppShell title={`Translations: ${lang}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Translation Dictionary (${lang.toUpperCase()})`}
          description="Update localized strings and UI text labels for this language locale."
          actions={
            <div className="flex items-center gap-2">
              <Link href="/languages">
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back to Languages
                </Button>
              </Link>
            </div>
          }
        />

        <form onSubmit={handleSave} className="space-y-6">
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between gap-3 pb-3 border-b border-white/10">
              <div className="w-full sm:max-w-xs">
                <Input
                  placeholder="Filter string keys..."
                  leftIcon={<Search className="w-4 h-4 text-gray-400" />}
                  value={searchFilter}
                  onChange={(e) => setSearchFilter(e.target.value)}
                />
              </div>
              <Button type="submit" variant="primary" size="md" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Translations
              </Button>
            </div>

            <div className="divide-y divide-white/[0.06] max-h-[500px] overflow-y-auto pr-2 space-y-3">
              {filteredKeys.length > 0 ? (
                filteredKeys.map((key) => (
                  <div key={key} className="pt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                    <div className="text-xs font-mono text-gray-400 select-all truncate" title={key}>
                      {key}
                    </div>
                    <Input
                      value={strings[key]}
                      onChange={(e) => handleStringChange(key, e.target.value)}
                    />
                  </div>
                ))
              ) : (
                <div className="py-8 text-center text-xs text-gray-500">
                  No translation keys match your filter.
                </div>
              )}
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
