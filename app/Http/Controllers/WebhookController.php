<?php

namespace App\Http\Controllers;

use App\Domain\Webhooks\WebhookService;
use App\Models\Webhook;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WebhookController extends Controller
{
    public function index(Request $r)
    {
        $w = $this->workspace($r);

        return Inertia::render('Webhooks/Index', [
            'webhooks' => Webhook::where('organization_id', $w->organization_id)->where('workspace_id', $w->id)
                ->with(['deliveries' => fn ($query) => $query->latest()->limit(1)])
                ->latest()->get()->makeHidden('secret'),
            'events' => $this->events(),
        ]);
    }

    public function store(Request $r, WebhookService $service)
    {
        $w = $this->workspace($r);
        $data = $this->validated($r);
        $service->validateUrl($data['url']);
        $secret = 'whsec_'.Str::random(48);
        $hook = Webhook::create($data + ['event' => $data['events'][0], 'organization_id' => $w->organization_id, 'workspace_id' => $w->id, 'method' => $data['method'] ?? 'POST', 'timeout_seconds' => $data['timeout_seconds'] ?? 10, 'secret' => $secret, 'is_active' => true, 'created_by' => $r->user()->id]);

        return back()->with('success', 'Webhook created. Copy secret now: '.$secret)->with('webhook_secret_'.$hook->id, $secret);
    }

    public function update(Request $r, Webhook $webhook, WebhookService $service)
    {
        $w = $this->workspace($r);
        $this->tenant($webhook, $w);
        $data = $this->validated($r);
        $service->validateUrl($data['url']);
        $webhook->update($data + ['event' => $data['events'][0]]);

        return back()->with('success', 'Webhook updated.');
    }

    public function toggle(Request $r, Webhook $webhook)
    {
        $w = $this->workspace($r);
        $this->tenant($webhook, $w);
        $webhook->update(['is_active' => ! $webhook->is_active]);

        return back()->with('success', $webhook->is_active ? 'Webhook enabled.' : 'Webhook disabled.');
    }

    public function rotate(Request $r, Webhook $webhook)
    {
        $w = $this->workspace($r);
        $this->tenant($webhook, $w);
        $secret = 'whsec_'.Str::random(48);
        $webhook->update(['secret' => $secret]);

        return back()->with('success', 'Webhook secret rotated. Copy it now: '.$secret)->with('webhook_secret_'.$webhook->id, $secret);
    }

    public function destroy(Request $r, Webhook $webhook)
    {
        $w = $this->workspace($r);
        $this->tenant($webhook, $w);
        $webhook->delete();

        return back()->with('success', 'Webhook deleted.');
    }

    public function test(Request $r, Webhook $webhook, WebhookService $service)
    {
        $w = $this->workspace($r);
        $this->tenant($webhook, $w);
        $service->dispatch($w->organization_id, $w->id, $webhook->event, ['test' => true, 'request_id' => $r->attributes->get('request_id')], 'test-'.Str::uuid());

        return back()->with('success', 'Test webhook queued.');
    }

    private function workspace(Request $r): Workspace
    {
        $w = Workspace::with('organization')->find($r->session()->get('active_workspace_id'));
        abort_unless($w && $r->user()->canInWorkspace('webhooks.manage', $w), 403);

        return $w;
    }

    private function tenant(Webhook $h, Workspace $w): void
    {
        abort_unless((int) $h->organization_id === (int) $w->organization_id && (int) $h->workspace_id === (int) $w->id, 404);
    }

    private function validated(Request $request): array
    {
        $events = $this->events();

        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'event' => ['nullable', 'required_without:events', Rule::in($events)],
            'events' => ['nullable', 'required_without:event', 'array', 'min:1'],
            'events.*' => ['required', Rule::in($events)],
            'method' => ['nullable', Rule::in(['POST', 'PUT'])],
            'timeout_seconds' => ['nullable', 'integer', 'between:1,30'],
        ]);

        $data['events'] = array_values(array_unique($data['events'] ?? [$data['event']]));
        unset($data['event']);

        return $data;
    }

    /** @return array<int, string> */
    private function events(): array
    {
        return ['order.paid', 'subscription.activated', 'invoice.posted', 'ticket.created', 'module.changed'];
    }
}
