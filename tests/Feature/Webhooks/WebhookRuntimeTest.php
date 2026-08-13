<?php

namespace Tests\Feature\Webhooks;

use App\Domain\Webhooks\WebhookService;
use App\Jobs\DeliverWebhook;
use App\Models\Organization;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    private Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $this->org = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->ws = Workspace::factory()->create(['organization_id' => $this->org->id]);
        $this->org->members()->attach($this->owner, ['role' => 'owner']);
        $this->ws->members()->attach($this->owner);
    }

    public function test_signed_delivery_is_idempotent_and_persisted(): void
    {
        Queue::fake();
        $hook = Webhook::create(['organization_id' => $this->org->id, 'workspace_id' => $this->ws->id, 'url' => 'https://example.com/hook', 'event' => 'order.paid', 'method' => 'POST', 'secret' => 'whsec_test', 'is_active' => true, 'timeout_seconds' => 10]);
        $service = app(WebhookService::class);
        $this->assertSame(1, $service->dispatch($this->org->id, $this->ws->id, 'order.paid', ['order_id' => 7], 'order-7'));
        $this->assertSame(1, $service->dispatch($this->org->id, $this->ws->id, 'order.paid', ['order_id' => 7], 'order-7'));
        $this->assertDatabaseCount('webhook_deliveries', 1);
        Queue::assertPushed(DeliverWebhook::class, 1);
        Http::fake(['https://example.com/hook' => Http::response('ok', 200)]);
        $delivery = WebhookDelivery::sole();
        (new DeliverWebhook($delivery->id))->handle($service);
        $this->assertSame('delivered', $delivery->refresh()->status);
        Http::assertSent(fn ($request) => str_starts_with($request->header('X-HiddenLeaf-Signature')[0] ?? '', 'sha256=') && $request->header('Idempotency-Key')[0] === $hook->id.':order-7');
    }

    public function test_private_destination_and_cross_tenant_delete_are_rejected(): void
    {
        $this->request()->post('/webhooks', ['url' => 'https://127.0.0.1/hook', 'event' => 'order.paid'])->assertServerError();
        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWs = Workspace::factory()->create(['organization_id' => $foreignOrg->id]);
        $hook = Webhook::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'url' => 'https://example.com', 'event' => 'order.paid', 'secret' => 'x']);
        $this->request()->delete("/webhooks/{$hook->id}")->assertNotFound();
    }

    private function request(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id]);
    }
}
