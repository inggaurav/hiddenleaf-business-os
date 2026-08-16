<?php

namespace Tests\Feature\Automation;

use App\Domain\Automation\Missions\MissionExecutor;
use App\Domain\Automation\Missions\MissionPlanner;
use App\Domain\Automation\Missions\MissionStateMachine;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\DTO\AiResponse;
use App\Domain\MrFox\Providers\FakeAiProvider;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\MrFoxMission;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxMissionsEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_mission_planning_execution_and_approval_governance(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Mission Plan', 'modules' => ['crm', 'taskly'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        $account = CommunicationAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'provider' => 'internal',
            'external_account_id' => 'internal_bot',
            'display_name' => 'Support Desk',
        ]);

        $conv = CommunicationConversation::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_id' => $account->id,
            'provider' => 'internal',
            'external_thread_id' => 'thread_lead_01',
            'subject' => 'Sales Inquiry',
            'participant_name' => 'John Doe',
            'participant_identifier' => 'john@example.com',
        ]);

        // Queue mock AI plan
        $fake = new FakeAiProvider;
        $fake->queueResponse(new AiResponse(
            content: json_encode([
                ['tool' => 'crm.search.leads', 'params' => ['query' => 'warm'], 'description' => 'Search warm leads'],
                ['tool' => 'business.dashboard.summary', 'params' => [], 'description' => 'Check business telemetry'],
                ['tool' => 'communications.send.reply', 'params' => ['conversation_id' => $conv->id, 'message_body' => 'Approved message text'], 'description' => 'Send approved outbound reply'],
            ]),
            provider: 'fake'
        ));
        app(ProviderRouter::class)->setFakeProvider($fake);

        $mission = MrFoxMission::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'name' => 'Follow up on Inactive Leads',
            'objective' => 'Review warm leads and send follow up replies.',
            'status' => 'draft',
            'allowed_tools' => ['crm.search.leads', 'business.dashboard.summary', 'communications.send.reply'],
        ]);

        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $ws);

        // 1. Plan Mission
        $planner = app(MissionPlanner::class);
        $planResult = $planner->plan($context, $mission);
        $this->assertCount(3, $planResult['steps']);
        $this->assertEquals('queued', $mission->fresh()->status);

        // 2. Execute Step 1 (Safe tool: crm.search.leads)
        $executor = app(MissionExecutor::class);
        $res1 = $executor->runNextStep($context, $mission->fresh());
        $this->assertEquals('running', $res1['status']);
        $this->assertEquals('crm.search.leads', $res1['tool']);
        $this->assertFalse($res1['requires_approval']);

        // 3. Execute Step 2 (Safe tool: business.dashboard.summary)
        $res2 = $executor->runNextStep($context, $mission->fresh());
        $this->assertEquals('running', $res2['status']);
        $this->assertEquals('business.dashboard.summary', $res2['tool']);
        $this->assertFalse($res2['requires_approval']);

        // 4. Execute Step 3 (High-risk tool: communications.send.reply) -> Must pause and propose approval
        $res3 = $executor->runNextStep($context, $mission->fresh());
        $this->assertEquals('waiting_for_approval', $res3['status']);
        $this->assertTrue($res3['requires_approval']);
        $this->assertEquals('waiting_for_approval', $mission->fresh()->status);

        // 5. Mission State Machine Controls (Pause / Resume / Cancel)
        $stateMachine = app(MissionStateMachine::class);
        $stateMachine->transition($mission->fresh(), 'paused', 'Operator review');
        $this->assertEquals('paused', $mission->fresh()->status);

        $stateMachine->transition($mission->fresh(), 'running');
        $this->assertEquals('running', $mission->fresh()->status);

        $stateMachine->transition($mission->fresh(), 'cancelled', 'Goal superseded');
        $this->assertEquals('cancelled', $mission->fresh()->status);
    }
}
