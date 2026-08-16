<?php

namespace Tests\Feature\Communications;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\DTO\AiResponse;
use App\Domain\MrFox\Providers\FakeAiProvider;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\Tools\CommunicationsDraftReplyTool;
use App\Domain\MrFox\Tools\CommunicationsGetTool;
use App\Domain\MrFox\Tools\CommunicationsSearchTool;
use App\Domain\MrFox\Tools\CommunicationsSendReplyTool;
use App\Domain\MrFox\Tools\CommunicationsSummarizeTool;
use App\Domain\MrFox\Tools\CommunicationsUnreadSummaryTool;
use App\Domain\MrFox\Tools\CommunicationsUrgentSummaryTool;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\MrFoxBrandProfile;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxCommunicationIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mr_fox_communication_tools_operate_with_brand_and_approval_governance(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Comm Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        // Create Brand Profile
        MrFoxBrandProfile::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Apex Agency',
            'industry' => 'Marketing',
            'tone_of_voice' => ['archetype' => 'Helpful and direct'],
            'is_default' => true,
        ]);

        $account = CommunicationAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'provider' => 'internal',
            'external_account_id' => 'support_bot',
            'display_name' => 'Support Desk',
        ]);

        $conv = CommunicationConversation::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_id' => $account->id,
            'provider' => 'internal',
            'external_thread_id' => 'thread_urgent_01',
            'subject' => 'Urgent: Invoice Question',
            'participant_name' => 'Alice Johnson',
            'participant_identifier' => 'alice@client.com',
            'priority_score' => 85,
            'unread_count' => 1,
            'status' => 'open',
            'last_message_preview' => 'Can you please clarify invoice item #42?',
        ]);

        CommunicationMessage::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'conversation_id' => $conv->id,
            'provider_message_id' => 'msg_001',
            'direction' => 'inbound',
            'sender_name' => 'Alice Johnson',
            'body_text' => 'Can you please clarify invoice item #42?',
            'delivery_status' => 'delivered',
            'sent_at' => now(),
        ]);

        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $ws);

        // 1. Search Tool
        $searchTool = app(CommunicationsSearchTool::class);
        $searchRes = $searchTool->execute($context, ['query' => 'Alice']);
        $this->assertTrue($searchRes->success);
        $this->assertCount(1, $searchRes->data);

        // 2. Get Tool
        $getTool = app(CommunicationsGetTool::class);
        $getRes = $getTool->execute($context, ['conversation_id' => $conv->id]);
        $this->assertTrue($getRes->success);
        $this->assertEquals('Alice Johnson', $getRes->data['participant_name']);
        $this->assertCount(1, $getRes->data['messages']);

        // 3. Unread Summary Tool
        $unreadTool = app(CommunicationsUnreadSummaryTool::class);
        $unreadRes = $unreadTool->execute($context, []);
        $this->assertTrue($unreadRes->success);
        $this->assertEquals(1, $unreadRes->data['total_unread_threads']);
        $this->assertEquals(1, $unreadRes->data['urgent_unread_threads']);

        // 4. Urgent Summary Tool
        $urgentTool = app(CommunicationsUrgentSummaryTool::class);
        $urgentRes = $urgentTool->execute($context, ['min_score' => 75]);
        $this->assertTrue($urgentRes->success);
        $this->assertCount(1, $urgentRes->data);

        // 5. Mock AI Provider for Summarize and Draft
        $fake = new FakeAiProvider;
        $fake->queueResponse(new AiResponse(
            content: 'Summary: Alice is inquiring about invoice item #42.',
            provider: 'fake'
        ));
        $fake->queueResponse(new AiResponse(
            content: 'Hi Alice! Thanks for reaching out. Invoice item #42 is for server setup. Let us know if you need anything else!',
            provider: 'fake'
        ));
        app(ProviderRouter::class)->setFakeProvider($fake);

        // Summarize Tool
        $sumTool = app(CommunicationsSummarizeTool::class);
        $sumRes = $sumTool->execute($context, ['conversation_id' => $conv->id]);
        $this->assertTrue($sumRes->success);
        $this->assertStringContainsString('Alice is inquiring', $sumRes->data['summary_text']);

        // Draft Reply Tool
        $draftTool = app(CommunicationsDraftReplyTool::class);
        $draftRes = $draftTool->execute($context, ['conversation_id' => $conv->id]);
        $this->assertTrue($draftRes->success);
        $this->assertStringContainsString('Hi Alice', $draftRes->data['draft_body']);
        $this->assertGreaterThanOrEqual(85, $draftRes->data['review_score']);

        // 6. Send Reply Tool
        $sendTool = app(CommunicationsSendReplyTool::class);
        $sendRes = $sendTool->execute($context, [
            'conversation_id' => $conv->id,
            'message_body' => $draftRes->data['draft_body'],
        ]);
        $this->assertTrue($sendRes->success);
        $this->assertEquals(2, CommunicationMessage::where('conversation_id', $conv->id)->count());
    }
}
