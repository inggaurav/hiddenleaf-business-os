import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, Mail } from 'lucide-react';

export default function EmailTemplateShow() {
  const { template, languages = [] } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    subject: template?.subject || '',
    content: template?.content || template?.body || 'Hello {user_name},\n\nYour account has been updated.\n\nRegards,\n{app_name}',
    language: 'en',
  });

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    post(`/email-templates/${template.id}`);
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
              <strong>Available Variables:</strong> {'{app_name}'}, {'{user_name}'}, {'{user_email}'}, {'{workspace_title}'}, {'{invoice_url}'}
            </div>

            <div className="pt-4 flex items-center justify-end gap-2">
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
