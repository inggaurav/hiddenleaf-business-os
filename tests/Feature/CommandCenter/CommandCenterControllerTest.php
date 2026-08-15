<?php

namespace Tests\Feature\CommandCenter;

use App\Models\MrFoxActionProposal;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandCenterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_center_and_approvals_endpoints(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan Admin', 'modules' => ['account', 'crm', 'hrm', 'productservice', 'taskly'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        $this->actingAs($user)->withSession([
            'active_organization_id' => $org->id,
            'active_workspace_id' => $ws->id,
        ]);

        // 1. GET /command-center (Inertia View)
        $resIndex = $this->get(route('command-center.index'));
        $resIndex->assertStatus(200);

        // 2. GET /command-center/health (JSON)
        $resHealth = $this->get(route('command-center.health'));
        $resHealth->assertStatus(200);
        $resHealth->assertJsonStructure(['overall', 'dimensions']);

        // 3. GET /command-center/priorities (JSON)
        $resPriorities = $this->get(route('command-center.priorities'));
        $resPriorities->assertStatus(200);

        // 4. GET /command-center/briefing (JSON)
        $resBriefing = $this->get(route('command-center.briefing', ['period' => 'today']));
        $resBriefing->assertStatus(200);
        $resBriefing->assertJsonStructure(['period', 'summary_headline', 'financial_snapshot']);

        // 5. GET /command-center/activity (JSON)
        $resActivity = $this->get(route('command-center.activity'));
        $resActivity->assertStatus(200);

        // 6. GET /command-center/approvals (Inertia View)
        $proposal = MrFoxActionProposal::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'tool_name' => 'crm.create.lead',
            'payload' => ['name' => 'Pending Partner'],
            'human_summary' => 'Create partner lead',
            'risk_level' => 'HIGH',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $resApprovals = $this->get(route('command-center.approvals.index'));
        $resApprovals->assertStatus(200);

        // 7. POST /command-center/approvals/{id}/reject
        $resReject = $this->post(route('command-center.approvals.reject', ['id' => $proposal->id]));
        $resReject->assertStatus(200);
        $this->assertEquals('rejected', $proposal->fresh()->status);
    }
}
