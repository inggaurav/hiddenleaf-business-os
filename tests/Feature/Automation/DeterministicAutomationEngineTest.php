<?php

namespace Tests\Feature\Automation;

use App\Domain\Automation\Execution\AutomationEngine;
use App\Models\AutomationRule;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeterministicAutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_deterministic_automation_slices_and_idempotency(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan A', 'modules' => ['crm', 'taskly'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        $engine = app(AutomationEngine::class);

        // 1. Slice: New qualified lead → Create Taskly Task
        $ruleLead = AutomationRule::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'name' => 'Follow up on Qualified Lead',
            'trigger_type' => 'crm.lead.stage_changed',
            'condition_config' => [
                'field' => 'stage',
                'operator' => 'equals',
                'value' => 'qualified',
            ],
            'action_config' => [
                [
                    'action' => 'tasks.create_task',
                    'input' => ['title' => 'Follow up with lead {{lead_name}}'],
                ],
            ],
            'enabled' => true,
        ]);

        $leadPayload = ['lead_id' => 101, 'lead_name' => 'Acme Corp', 'stage' => 'qualified'];
        $runs = $engine->dispatch($ws->id, 'crm.lead.stage_changed', $leadPayload);
        $this->assertCount(1, $runs);
        $this->assertEquals('completed', $runs[0]->status);
        $this->assertDatabaseHas('taskly_tasks', ['workspace_id' => $ws->id, 'title' => 'Follow up with lead Acme Corp']);

        // Idempotency: Dispatching same payload again must return existing run and NOT create another task
        $runs2 = $engine->dispatch($ws->id, 'crm.lead.stage_changed', $leadPayload);
        $this->assertCount(1, $runs2);
        $this->assertEquals(1, TasklyTask::where('workspace_id', $ws->id)->count());

        // 2. Slice: Urgent Inbound Communication → Create Notification
        $ruleUrgent = AutomationRule::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'name' => 'Urgent Message Alert',
            'trigger_type' => 'communications.message.urgent',
            'condition_config' => [
                'field' => 'priority_score',
                'operator' => '>=',
                'value' => 75,
            ],
            'action_config' => [
                [
                    'action' => 'notifications.create',
                    'input' => ['title' => 'Urgent message from {{contact}}', 'message' => 'Needs reply ASAP'],
                ],
            ],
            'enabled' => true,
        ]);

        $urgentPayload = ['id' => 501, 'contact' => 'VIP Client', 'priority_score' => 90];
        $runsUrgent = $engine->dispatch($ws->id, 'communications.message.urgent', $urgentPayload);
        $this->assertCount(1, $runsUrgent);
        $this->assertEquals('completed', $runsUrgent[0]->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);

        // 3. Loop Prevention: depth > 5 must be suppressed
        $loopRuns = $engine->dispatch($ws->id, 'crm.lead.stage_changed', ['lead_id' => 999, 'stage' => 'qualified'], null, 6);
        $this->assertEmpty($loopRuns);
    }

    public function test_tenant_isolation_in_automations(): void
    {
        $this->seed();

        // Workspace A
        $userA = User::factory()->create(['role' => 'super_admin']);
        $planA = Plan::create(['name' => 'Plan A', 'modules' => ['crm'], 'status' => true, 'created_by' => $userA->id]);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id, 'plan_id' => $planA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $userA->id]);

        // Workspace B
        $userB = User::factory()->create(['role' => 'super_admin']);
        $planB = Plan::create(['name' => 'Plan B', 'modules' => ['crm'], 'status' => true, 'created_by' => $userB->id]);
        $orgB = Organization::factory()->create(['owner_id' => $userB->id, 'plan_id' => $planB->id]);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'created_by' => $userB->id]);

        $ruleA = AutomationRule::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'name' => 'Rule Workspace A',
            'trigger_type' => 'crm.lead.created',
            'action_config' => [['action' => 'tasks.create_task', 'input' => ['title' => 'Task A']]],
            'enabled' => true,
        ]);

        // Dispatching in Workspace B must not trigger Workspace A's rule
        $engine = app(AutomationEngine::class);
        $runs = $engine->dispatch($wsB->id, 'crm.lead.created', ['lead_id' => 1]);
        $this->assertEmpty($runs);
    }
}
