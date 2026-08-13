<?php

namespace Tests\Feature\Modules;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\TasklyTimesheet;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasklyModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Organization $organization;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $plan = Plan::create(['name' => 'Taskly', 'modules' => ['taskly'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach([$this->owner->id, $this->member->id]);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'taskly']);
    }

    public function test_project_task_timesheet_and_budget_cost_workflow(): void
    {
        $this->request()->post('/taskly/projects', ['name' => 'ERP Rollout', 'budget' => 10000, 'manager_id' => $this->owner->id, 'members' => [['user_id' => $this->member->id, 'hourly_rate' => 100]], 'stages' => [['name' => 'Backlog'], ['name' => 'Done', 'is_complete' => true]]])->assertSessionHasNoErrors();
        $project = TasklyProject::with('stages')->firstOrFail();
        $backlog = $project->stages->firstWhere('name', 'Backlog');
        $done = $project->stages->firstWhere('name', 'Done');
        $this->request()->post('/taskly/tasks', ['project_id' => $project->id, 'stage_id' => $backlog->id, 'assigned_to' => $this->member->id, 'title' => 'Configure finance', 'priority' => 'high', 'estimated_hours' => 10])->assertSessionHasNoErrors();
        $task = TasklyTask::sole();
        $this->request()->post("/taskly/tasks/{$task->id}/comments", ['body' => 'Started'])->assertSessionHasNoErrors();
        $this->request()->post('/taskly/timesheets', ['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $this->member->id, 'work_date' => '2026-08-14', 'hours' => 4, 'description' => 'Configuration'])->assertSessionHasNoErrors();
        $time = TasklyTimesheet::sole();
        $this->request()->post("/taskly/timesheets/{$time->id}/approve")->assertSessionHasNoErrors();
        $this->request()->post("/taskly/tasks/{$task->id}/move", ['stage_id' => $done->id])->assertSessionHasNoErrors();
        $this->assertNotNull($task->refresh()->completed_at);
        $this->assertSame('approved', $time->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'task.completed', 'entity_id' => (string) $task->id]);
        $this->request()->get('/taskly')->assertInertia(fn ($page) => $page->where("costs.{$project->id}", 400));
    }

    public function test_nonmember_assignment_and_cross_tenant_task_are_rejected(): void
    {
        $outsider = User::factory()->create();
        $this->request()->post('/taskly/projects', ['name' => 'Bad', 'members' => [['user_id' => $outsider->id]], 'stages' => [['name' => 'Todo']]])->assertStatus(422);
        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWs = Workspace::factory()->create(['organization_id' => $foreignOrg->id]);
        $project = TasklyProject::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'name' => 'Foreign', 'status' => 'active']);
        $stage = $project->stages()->create(['name' => 'Todo']);
        $task = TasklyTask::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'project_id' => $project->id, 'stage_id' => $stage->id, 'title' => 'Foreign', 'priority' => 'low']);
        $this->request()->post("/taskly/tasks/{$task->id}/move", ['stage_id' => $stage->id])->assertNotFound();
    }

    private function request(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id]);
    }
}
