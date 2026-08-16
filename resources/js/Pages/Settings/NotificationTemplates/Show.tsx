import React from 'react';
import { useForm, Link, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, Bell } from 'lucide-react';

export default function NotificationTemplateShow() {
  const { template, currTemplate, languages = [], currentLang = 'en' } = usePage<any>().props;
  const [preview, setPreview] = React.useState(false);

  const { data, setData, patch, processing, errors } = useForm({
    content: currTemplate?.content || 'New notification: {message}',
    lang: currentLang,
    is_enabled: template?.is_enabled !== false,
  });

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    patch(`/notification-templates/${template.id}`);
  };

  return (
    <AppShell title={`Customize: ${template?.name}`}>
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Alert: ${template?.name}`}
          description="Update in-app notification summary copy."
          actions={
            <Link href="/notification-templates">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSave}>
          <Card level={0} className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <select className="rounded-lg border border-white/10 bg-gray-950 px-3 py-2 text-sm" value={data.lang} onChange={(e) => router.get(`/notification-templates/${template.id}`, { lang: e.target.value })}>
                {languages.map((language: any) => <option key={language.code} value={language.code}>{language.name}</option>)}
              </select>
              <label className="flex items-center gap-2 text-sm text-gray-300"><input type="checkbox" checked={data.is_enabled} onChange={(e) => setData('is_enabled', e.target.checked)} />Enabled</label>
            </div>
            <Textarea
              label="Notification Message"
              rows={4}
              value={data.content}
              onChange={(e) => setData('content', e.target.value)}
              error={errors.content}
              required
            />

            <div className="rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-xs text-amber-200"><strong>Available variables:</strong> {Array.isArray(template?.variables) ? template.variables.join(', ') : (template?.variables || '{message}, {user_name}, {workspace_title}')}</div>
            {preview && <div className="rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-gray-300">{data.content}</div>}

            <div className="pt-4 flex flex-wrap items-center justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setPreview(!preview)}>{preview ? 'Hide preview' : 'Preview'}</Button>
              <Button type="button" variant="outline" onClick={() => router.post(`/notification-templates/${template.id}/reset`, { lang: data.lang })}>Reset default</Button>
              <Link href="/notification-templates">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Template
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
