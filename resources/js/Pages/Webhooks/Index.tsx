import React from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';

type Delivery = { status: string; response_status?: number; delivered_at?: string; error?: string };
type Hook = { id: number; url: string; events?: string[]; event: string; method: string; timeout_seconds: number; is_active: boolean; deliveries?: Delivery[] };

export default function Webhooks() {
  const { webhooks = [], events = [] } = usePage<any>().props as { webhooks: Hook[]; events: string[] };
  const form = useForm({ url: '', events: [] as string[], method: 'POST', timeout_seconds: 10 });

  const toggleEvent = (event: string) => form.setData('events', form.data.events.includes(event)
    ? form.data.events.filter((item) => item !== event)
    : [...form.data.events, event]);

  return (
    <AppShell title="Webhooks">
      <div className="space-y-6">
        <SectionHeader title="Webhook subscriptions" description="Create signed tenant callbacks, subscribe to events, test delivery, rotate secrets, and inspect the latest status." />
        <Card level={0} className="space-y-4">
          <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); form.post('/webhooks', { onSuccess: () => form.reset() }); }}>
            <div className="grid gap-4 md:grid-cols-3">
              <div className="md:col-span-2"><Input label="HTTPS endpoint" value={form.data.url} onChange={(event) => form.setData('url', event.target.value)} error={form.errors.url} required /></div>
              <Input label="Timeout (seconds)" type="number" value={form.data.timeout_seconds} onChange={(event) => form.setData('timeout_seconds', Number(event.target.value))} error={form.errors.timeout_seconds} />
            </div>
            <div>
              <div className="mb-2 text-sm text-gray-300">Event subscriptions</div>
              <div className="flex flex-wrap gap-2">{events.map((event) => <label key={event} className="flex items-center gap-2 rounded border border-white/10 px-3 py-2 text-xs"><input type="checkbox" checked={form.data.events.includes(event)} onChange={() => toggleEvent(event)} />{event}</label>)}</div>
              {form.errors.events && <div className="mt-1 text-xs text-red-400">{form.errors.events}</div>}
            </div>
            <Button type="submit" loading={form.processing}>Create webhook</Button>
          </form>
        </Card>

        <div className="space-y-3">
          {webhooks.map((hook) => {
            const last = hook.deliveries?.[0];
            return <Card key={hook.id} level={0} className="space-y-3">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div><div className="font-mono text-sm text-white break-all">{hook.url}</div><div className="mt-2 flex flex-wrap gap-1">{(hook.events?.length ? hook.events : [hook.event]).map((event) => <Badge key={event} size="sm">{event}</Badge>)}</div></div>
                <Badge variant={hook.is_active ? 'success' : 'neutral'}>{hook.is_active ? 'Enabled' : 'Disabled'}</Badge>
              </div>
              <div className="text-xs text-gray-400">{hook.method} · {hook.timeout_seconds}s · Latest delivery: {last ? `${last.status}${last.response_status ? ` (${last.response_status})` : ''}` : 'No deliveries'}</div>
              {last?.error && <div className="text-xs text-red-400">{last.error}</div>}
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={() => router.post(`/webhooks/${hook.id}/test`)}>Send test</Button>
                <Button size="sm" variant="outline" onClick={() => router.patch(`/webhooks/${hook.id}/toggle`)}>{hook.is_active ? 'Disable' : 'Enable'}</Button>
                <Button size="sm" variant="outline" onClick={() => router.post(`/webhooks/${hook.id}/rotate-secret`)}>Rotate secret</Button>
                <Button size="sm" variant="danger" onClick={() => { if (confirm('Delete this webhook?')) router.delete(`/webhooks/${hook.id}`); }}>Delete</Button>
              </div>
            </Card>;
          })}
          {webhooks.length === 0 && <Card level={0}><div className="py-8 text-center text-sm text-gray-400">No webhook subscriptions yet.</div></Card>}
        </div>
      </div>
    </AppShell>
  );
}
