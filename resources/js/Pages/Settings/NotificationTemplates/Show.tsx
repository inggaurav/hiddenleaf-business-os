import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, Bell } from 'lucide-react';

export default function NotificationTemplateShow() {
  const { template } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    content: template?.content || '',
  });

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    post(`/notification-templates/${template.id}`);
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
            <Textarea
              label="Notification Message"
              rows={4}
              value={data.content}
              onChange={(e) => setData('content', e.target.value)}
              error={errors.content}
              required
            />

            <div className="pt-4 flex items-center justify-end gap-2">
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
