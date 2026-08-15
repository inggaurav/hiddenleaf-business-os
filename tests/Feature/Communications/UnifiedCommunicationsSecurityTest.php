<?php

namespace Tests\Feature\Communications;

use App\Domain\Communications\Actions\CommunicationSendService;
use App\Domain\Communications\Providers\GmailProvider;
use App\Domain\Communications\Providers\SlackProvider;
use App\Domain\Communications\Providers\WhatsAppCloudProvider;
use App\Domain\Communications\Webhooks\CommunicationWebhookService;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiedCommunicationsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_isolation_prevents_cross_workspace_conversation_access(): void
    {
        $this->seed();

        // Tenant A
        $userA = User::factory()->create(['role' => 'super_admin']);
        $planA = Plan::create(['name' => 'Plan A', 'modules' => ['crm'], 'status' => true, 'created_by' => $userA->id]);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id, 'plan_id' => $planA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $userA->id]);
        $orgA->members()->attach($userA, ['role' => 'owner']);
        $wsA->members()->attach($userA);

        // Tenant B
        $userB = User::factory()->create(['role' => 'super_admin']);
        $planB = Plan::create(['name' => 'Plan B', 'modules' => ['crm'], 'status' => true, 'created_by' => $userB->id]);
        $orgB = Organization::factory()->create(['owner_id' => $userB->id, 'plan_id' => $planB->id]);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'created_by' => $userB->id]);
        $orgB->members()->attach($userB, ['role' => 'owner']);
        $wsB->members()->attach($userB);

        $accA = CommunicationAccount::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'provider' => 'gmail',
            'external_account_id' => 'alpha@example.com',
            'display_name' => 'Alpha Support',
            'email' => 'alpha@example.com',
        ]);

        $convA = CommunicationConversation::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'account_id' => $accA->id,
            'provider' => 'gmail',
            'external_thread_id' => 'thread_alpha_secret_01',
            'subject' => 'Confidential Merger Terms',
            'participant_name' => 'Secret VIP Contact',
            'participant_identifier' => 'vip@acme.com',
        ]);

        // User B attempts to access Tenant A's conversation thread
        $userB->current_workspace_id = $wsB->id;
        $response = $this->actingAs($userB)->getJson("/api/v1/communications/conversations/{$convA->id}");
        $response->assertStatus(404);
    }

    public function test_outbound_idempotency_prevents_duplicate_sends_during_concurrent_requests(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan A', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $account = CommunicationAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'provider' => 'internal',
            'external_account_id' => 'internal_user_1',
            'display_name' => 'System Support',
        ]);

        $conv = CommunicationConversation::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_id' => $account->id,
            'provider' => 'internal',
            'external_thread_id' => 'internal_1_2',
            'subject' => 'Ticket Inquiry',
            'participant_name' => 'Client',
            'participant_identifier' => '2',
        ]);

        $sendService = app(CommunicationSendService::class);
        $idempotencyKey = 'idemp_key_unique_123';

        // First attempt
        $res1 = $sendService->sendReply($conv, 'Thank you for contacting us.', $idempotencyKey);
        $this->assertTrue($res1->success);

        // Second attempt with exact same idempotency key
        $res2 = $sendService->sendReply($conv, 'Thank you for contacting us.', $idempotencyKey);
        $this->assertTrue($res2->success);
        $this->assertTrue($res2->metadata['idempotent_duplicate'] ?? false);

        // Verify only 1 message was created in database
        $this->assertEquals(1, CommunicationMessage::where('conversation_id', $conv->id)->count());
    }

    public function test_whatsapp_webhook_signature_verification_and_challenge(): void
    {
        $provider = new WhatsAppCloudProvider();
        $secret = 'super_secret_whatsapp_key';
        $payload = json_encode(['entry' => []]);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        // Valid signature
        $this->assertTrue($provider->verifyWebhook(['x-hub-signature-256' => $signature], $payload, $secret));

        // Tampered signature
        $this->assertFalse($provider->verifyWebhook(['x-hub-signature-256' => 'sha256=invalid_hash'], $payload, $secret));
    }

    public function test_slack_webhook_signing_secret_and_replay_protection(): void
    {
        $provider = new SlackProvider();
        $secret = 'slack_secret_123';
        $payload = 'command=/help';

        // Valid timestamp
        $timestamp = (string) time();
        $sig = 'v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$payload}", $secret);
        $headers = [
            'x-slack-request-timestamp' => $timestamp,
            'x-slack-signature' => $sig,
        ];
        $this->assertTrue($provider->verifyWebhook($headers, $payload, $secret));

        // Replay attack: expired timestamp (600s old)
        $oldTimestamp = (string) (time() - 600);
        $oldSig = 'v0=' . hash_hmac('sha256', "v0:{$oldTimestamp}:{$payload}", $secret);
        $oldHeaders = [
            'x-slack-request-timestamp' => $oldTimestamp,
            'x-slack-signature' => $oldSig,
        ];
        $this->assertFalse($provider->verifyWebhook($oldHeaders, $payload, $secret));
    }
}
