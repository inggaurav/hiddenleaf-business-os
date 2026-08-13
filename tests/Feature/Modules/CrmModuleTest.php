<?php

namespace Tests\Feature\Modules;

use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $plan = Plan::create(['name' => 'CRM', 'modules' => ['lead'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach($this->owner);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'lead']);
    }

    public function test_lead_pipeline_conversion_activity_and_won_deal(): void
    {
        $this->request()->post('/crm/pipelines', ['name' => 'Sales', 'is_default' => true, 'stages' => [['name' => 'New', 'probability' => 10], ['name' => 'Won', 'probability' => 100, 'is_closed' => true, 'outcome' => 'won'], ['name' => 'Lost', 'probability' => 0, 'is_closed' => true, 'outcome' => 'lost']]])->assertSessionHasNoErrors();
        $pipeline = CrmPipeline::with('stages')->firstOrFail();
        $new = $pipeline->stages->firstWhere('name', 'New');
        $won = $pipeline->stages->firstWhere('name', 'Won');
        $this->request()->post('/crm/leads', ['pipeline_id' => $pipeline->id, 'stage_id' => $new->id, 'assigned_to' => $this->owner->id, 'name' => 'Acme Opportunity', 'email' => 'buyer@acme.test', 'company' => 'Acme', 'estimated_value' => 25000])->assertSessionHasNoErrors();
        $lead = CrmLead::sole();
        $this->request()->post("/crm/lead/{$lead->id}/notes", ['body' => 'Discovery completed'])->assertSessionHasNoErrors();
        $this->request()->post("/crm/lead/{$lead->id}/activities", ['type' => 'meeting', 'title' => 'Product demo', 'assigned_to' => $this->owner->id])->assertSessionHasNoErrors();
        $this->request()->post("/crm/leads/{$lead->id}/convert", [])->assertSessionHasNoErrors();
        $deal = CrmDeal::sole();
        $this->assertSame('converted', $lead->refresh()->status);
        $this->assertSame('25000.00', $deal->value);
        $this->request()->post("/crm/deals/{$deal->id}/move", ['stage_id' => $won->id])->assertSessionHasNoErrors();
        $this->assertSame('won', $deal->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deal.won', 'entity_id' => (string) $deal->id]);
    }

    public function test_cross_tenant_deal_and_nonmember_assignment_are_rejected(): void
    {
        $pipeline = CrmPipeline::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Sales']);
        $stage = $pipeline->stages()->create(['name' => 'New', 'position' => 0]);
        $outsider = User::factory()->create();
        $this->request()->post('/crm/leads', ['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'assigned_to' => $outsider->id, 'name' => 'Poisoned'])->assertStatus(422);
        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWs = Workspace::factory()->create(['organization_id' => $foreignOrg->id]);
        $foreignPipeline = CrmPipeline::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'name' => 'Foreign']);
        $foreignStage = $foreignPipeline->stages()->create(['name' => 'New', 'position' => 0]);
        $deal = CrmDeal::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'pipeline_id' => $foreignPipeline->id, 'stage_id' => $foreignStage->id, 'name' => 'Foreign', 'value' => 1, 'status' => 'open']);
        $this->request()->post("/crm/deals/{$deal->id}/move", ['stage_id' => $foreignStage->id])->assertNotFound();
    }

    private function request(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id]);
    }
}
