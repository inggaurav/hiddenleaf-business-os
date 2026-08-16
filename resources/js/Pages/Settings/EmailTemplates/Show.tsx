import React from 'react';
import { useForm, Link, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, Mail } from 'lucide-react';

export default function EmailTemplateShow() {
  const { template, currTemplate, languages = [], currentLang = 'en' } = usePage<any>().props;
  const [preview, setPreview] = React.useState(false);

  const { data, setData, patch, processing, errors } = useForm({
    subject: currTemplate?.subject || template?.subject || '',
    content: currTemplate?.content || template?.body || 'Hello {user_name},\n\nYour account has been updated.\n\nRegards,\n{app_name}',
    lang: currentLang,
    is_enabled: template?.is_enabled !== false,
  });

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    patch(`/email-templates/${template.id}`);
  };

  return (
    <AppShell title={`Customize: ${template?.name}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit Email: ${template?.name}`}
          description="Update subject line and email body markup for transactional dispatches."
          actions={
            <Link href="/email-templates">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSave}>
          <Card level={0} className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <select className="rounded-lg border border-white/10 bg-gray-950 px-3 py-2 text-sm" value={data.lang} onChange={(e) => router.get(`/email-templates/${template.id}`, { lang: e.target.value })}>
                {languages.map((language: any) => <option key={language.code} value={language.code}>{language.name}</option>)}
              </select>
              <label className="flex items-center gap-2 text-sm text-gray-300"><input type="checkbox" checked={data.is_enabled} onChange={(e) => setData('is_enabled', e.target.checked)} />Enabled</label>
            </div>
            <Input
              label="Subject Line"
              value={data.subject}
              onChange={(e) => setData('subject', e.target.value)}
              error={errors.subject}
              required
            />

            <Textarea
              label="Email Body (Plaintext or HTML)"
              rows={8}
              value={data.content}
              onChange={(e) => setData('content', e.target.value)}
              required
            />

            <div className="p-3.5 rounded-xl bg-purple-950/20 border border-purple-500/20 text-xs text-purple-300">
              <strong>Available Variables:</strong> {Array.isArray(template?.variables) ? template.variables.join(', ') : (template?.variables || '{app_name}, {user_name}, {user_email}, {workspace_title}, {invoice_url}')}
            </div>

            {preview && <div className="rounded-xl border border-white/10 bg-white/[0.03] p-4"><div className="mb-2 font-semibold">{data.subject}</div><div className="whitespace-pre-wrap text-sm text-gray-300" dangerouslySetInnerHTML={{ __html: data.content }} /></div>}

            <div className="pt-4 flex flex-wrap items-center justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setPreview(!preview)}>{preview ? 'Hide preview' : 'Preview'}</Button>
              <Button type="button" variant="outline" onClick={() => router.post(`/email-templates/${template.id}/reset`, { lang: data.lang })}>Reset default</Button>
              <Link href="/email-templates">
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
