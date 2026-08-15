<?php

namespace Tests\Feature\MrFox;

use App\Models\MrFoxConversation;
use App\Models\MrFoxMessage;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxConversationIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_b_cannot_view_or_send_messages_to_tenant_a_conversation(): void
    {
        $this->seed();

        // Tenant A
        $userA = User::factory()->create(['role' => 'company_admin']);
        $planA = Plan::create(['name' => 'Plan A', 'modules' => ['crm'], 'status' => true, 'created_by' => $userA->id]);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id, 'plan_id' => $planA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $userA->id]);
        $orgA->members()->attach($userA, ['role' => 'owner']);
        $wsA->members()->attach($userA);

        // Tenant B
        $userB = User::factory()->create(['role' => 'company_admin']);
        $planB = Plan::create(['name' => 'Plan B', 'modules' => ['crm'], 'status' => true, 'created_by' => $userB->id]);
        $orgB = Organization::factory()->create(['owner_id' => $userB->id, 'plan_id' => $planB->id]);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'created_by' => $userB->id]);
        $orgB->members()->attach($userB, ['role' => 'owner']);
        $wsB->members()->attach($userB);

        // Create confidential conversation for Tenant A
        $convA = MrFoxConversation::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'user_id' => $userA->id,
            'title' => 'Secret Acquisition Strategy',
        ]);

        MrFoxMessage::create([
            'conversation_id' => $convA->id,
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'user_id' => $userA->id,
            'role' => 'user',
            'content' => 'Top secret financial acquisition plan details.',
        ]);

        // User B attempts to access Tenant A conversation via API
        $response = $this->actingAs($userB)->getJson("/api/v1/mr-fox/conversations/{$convA->id}");
        $response->assertStatus(404);

        // User B attempts to post message to Tenant A conversation
        $chatResponse = $this->actingAs($userB)->postJson('/api/v1/mr-fox/chat', [
            'conversation_id' => $convA->id,
            'message' => 'Injecting into foreign conversation',
        ]);
        $chatResponse->assertStatus(404);
    }
}
