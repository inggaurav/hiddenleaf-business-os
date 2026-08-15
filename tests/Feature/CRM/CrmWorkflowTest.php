<?php

namespace Tests\Feature\CRM;

use App\Models\AccountCustomer;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CrmWebform;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private Workspace $workspace;

    private CrmPipeline $pipeline;

    private CrmStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'CRM Enterprise', 'modules' => ['lead', 'account'], 'status' => true, 'created_by' => $this->user->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'lead']);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'account']);

        $this->pipeline = CrmPipeline::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Pipeline',
            'is_default' => true,
        ]);

        $this->stage = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'New Lead',
            'position' => 0,
            'probability' => 20,
        ]);
    }

    public function test_lead_creation_and_conversion_to_customer_and_deal(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/crm/leads', [
                'pipeline_id' => $this->pipeline->id,
                'stage_id' => $this->stage->id,
                'name' => 'John Wick Enterprises',
                'email' => 'john@continental.com',
                'phone' => '+15550199',
                'company' => 'Continental Hotel Group',
                'estimated_value' => 75000.00,
            ]);

        $response->assertSessionHasNoErrors();
        $lead = CrmLead::where('email', 'john@continental.com')->firstOrFail();
        $this->assertSame('open', $lead->status);

        // Convert lead to Deal + Customer
        $convertResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post("/crm/leads/{$lead->id}/convert", [
                'name' => 'Continental Security Upgrade',
                'value' => 80000.00,
                'create_customer' => true,
            ]);

        $convertResponse->assertSessionHasNoErrors();

        $this->assertSame('converted', $lead->refresh()->status);
        $this->assertNotNull($lead->converted_at);

        // Verify Deal was created with lead details
        $deal = CrmDeal::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('Continental Security Upgrade', $deal->name);
        $this->assertSame('80000.00', (string) $deal->value);

        // Verify Customer was created in workspace
        $customer = AccountCustomer::forWorkspace($this->organization->id, $this->workspace->id)->where('email', 'john@continental.com')->firstOrFail();
        $this->assertSame('John Wick Enterprises', $customer->name);
        $this->assertSame('+15550199', $customer->contact);

        // Re-conversion of already converted lead must be rejected (idempotency)
        $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post("/crm/leads/{$lead->id}/convert", ['name' => 'Duplicate Attempt'])
            ->assertStatus(422);
    }

    public function test_crm_webform_creation_and_public_lead_capture(): void
    {
        // 1. Create Webform
        $webformResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/crm/webforms', [
                'name' => 'Website Contact Us Form',
                'pipeline_id' => $this->pipeline->id,
                'stage_id' => $this->stage->id,
            ]);

        $webformResponse->assertSessionHasNoErrors();
        $webform = CrmWebform::where('name', 'Website Contact Us Form')->firstOrFail();
        $this->assertNotEmpty($webform->token);

        // 2. Submit Public Webform (Unauthenticated public call)
        $publicResponse = $this->postJson("/crm/forms/{$webform->token}/submit", [
            'name' => 'Bruce Wayne',
            'email' => 'bruce@wayne-enterprises.com',
            'phone' => '+15550100',
            'company' => 'Wayne Enterprises',
            'estimated_value' => 500000.00,
            'message' => 'Interested in enterprise cloud infrastructure security.',
        ]);

        $publicResponse->assertOk()->assertJson(['status' => 'success']);

        // Verify lead was created in the correct tenant workspace
        $capturedLead = CrmLead::forWorkspace($this->organization->id, $this->workspace->id)
            ->where('email', 'bruce@wayne-enterprises.com')
            ->firstOrFail();

        $this->assertSame('Bruce Wayne', $capturedLead->name);
        $this->assertSame($this->pipeline->id, $capturedLead->pipeline_id);
        $this->assertSame($this->stage->id, $capturedLead->stage_id);

        // Verify note was created with message
        $this->assertDatabaseHas('crm_notes', [
            'workspace_id' => $this->workspace->id,
            'subject_id' => $capturedLead->id,
            'body' => 'Interested in enterprise cloud infrastructure security.',
        ]);
    }

    public function test_cross_tenant_lead_and_deal_isolation(): void
    {
        $foreignUser = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignUser->id]);
        $foreignWs = Workspace::factory()->create(['organization_id' => $foreignOrg->id]);

        $foreignPipeline = CrmPipeline::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'name' => 'Foreign Pipeline']);
        $foreignStage = CrmStage::create(['pipeline_id' => $foreignPipeline->id, 'name' => 'Foreign Stage']);
        $foreignLead = CrmLead::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'pipeline_id' => $foreignPipeline->id, 'stage_id' => $foreignStage->id, 'name' => 'Foreign Target']);

        // Attempt to move foreign lead
        $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post("/crm/leads/{$foreignLead->id}/move", ['stage_id' => $this->stage->id])
            ->assertNotFound();

        // Attempt to convert foreign lead
        $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post("/crm/leads/{$foreignLead->id}/convert", ['name' => 'Foreign Hack'])
            ->assertNotFound();
    }
}
