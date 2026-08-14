import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function HelpdeskCreate() {
  const { categories = [] } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    title: '',
    name: '',
    email: '',
    category_id: categories[0]?.id || '',
    description: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/helpdesk-tickets');
  };

  return (
    <AppShell title="Open Support Ticket">
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title="Open New Support Ticket"
          description="Log a new customer issue, inquiry, or incident ticket."
          actions={
            <Link href="/helpdesk-tickets">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Tickets
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <Input
              label="Ticket Subject"
              placeholder="e.g. Issue connecting to API webhooks"
              value={data.title}
              onChange={(e) => setData('title', e.target.value)}
              error={errors.title}
              required
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Requester Name"
                placeholder="e.g. David Miller"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                error={errors.name}
                required
              />
              <Input
                label="Requester Email"
                type="email"
                placeholder="e.g. david@client.com"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                error={errors.email}
                required
              />
            </div>

            {categories.length > 0 && (
              <Select
                label="Ticket Category"
                value={data.category_id}
                onChange={(e) => setData('category_id', e.target.value)}
              >
                {categories.map((c: any) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </Select>
            )}

            <Textarea
              label="Detailed Issue Description"
              placeholder="Provide context, reproduction steps, or error messages..."
              rows={4}
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              error={errors.description}
              required
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/helpdesk-tickets">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Submit Ticket
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
