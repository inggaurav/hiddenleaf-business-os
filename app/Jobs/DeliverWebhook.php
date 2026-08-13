<?php

namespace App\Jobs;

use App\Domain\Webhooks\WebhookService;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public $tries = 5;

    public function __construct(public string $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(WebhookService $service): void
    {
        $delivery = WebhookDelivery::with('webhook')->findOrFail($this->deliveryId);
        if ($delivery->status === 'delivered') {
            return;
        }$hook = $delivery->webhook;
        $service->validateUrl($hook->url);
        $body = json_encode(['id' => $delivery->id, 'event' => $delivery->event, 'created_at' => $delivery->created_at->toIso8601String(), 'data' => $delivery->payload], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $hook->secret);
        try {
            $response = Http::timeout($hook->timeout_seconds)->withHeaders(['Content-Type' => 'application/json', 'X-HiddenLeaf-Event' => $delivery->event, 'X-HiddenLeaf-Delivery' => $delivery->id, 'X-HiddenLeaf-Timestamp' => $timestamp, 'X-HiddenLeaf-Signature' => 'sha256='.$signature, 'Idempotency-Key' => $delivery->idempotency_key])->withBody($body, 'application/json')->send($hook->method, $hook->url);
            $delivery->increment('attempts');
            if (! $response->successful()) {
                throw new \RuntimeException('Webhook returned HTTP '.$response->status());
            }$delivery->update(['status' => 'delivered', 'response_status' => $response->status(), 'response_body' => Str::limit($response->body(), 2000), 'delivered_at' => now(), 'error' => null]);
        } catch (Throwable$e) {
            $delivery->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 2000), 'next_attempt_at' => now()->addSeconds($this->backoff()[min($delivery->attempts, count($this->backoff()) - 1)])]);
            throw $e;
        }
    }
}
