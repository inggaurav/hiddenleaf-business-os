<?php

namespace App\Domain\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;
use RuntimeException;

class WebhookService
{
    public function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Webhook URLs must use HTTPS.');
        }$records = dns_get_record($parts['host'], DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            throw new RuntimeException('Webhook hostname cannot be resolved.');
        }foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Webhook destinations cannot resolve to private or reserved addresses.');
            }
        }
    }

    public function dispatch(int $organizationId, int $workspaceId, string $event, array $payload, string $idempotencyKey): int
    {
        $hooks = Webhook::where('organization_id', $organizationId)->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('event', $event)->orWhereJsonContains('events', $event))
            ->get();
        foreach ($hooks as $hook) {
            $delivery = WebhookDelivery::firstOrCreate(['idempotency_key' => $hook->id.':'.$idempotencyKey], ['id' => (string) Str::uuid(), 'webhook_id' => $hook->id, 'event' => $event, 'payload' => $payload, 'status' => 'pending']);
            if ($delivery->wasRecentlyCreated) {
                DeliverWebhook::dispatch($delivery->id);
            }
        }

        return $hooks->count();
    }
}
