<?php

namespace Tests\Feature;

use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandingLeadPanelImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $owner;
    private Organization $organization;
    private Workspace $workspace;
    private CrmPipeline $pipeline;
    private CrmStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->owner = User::factory()->create(['role' => 'company_admin']);

        $plan = Plan::create([
            'name' => 'Complete Plan',
            'modules' => ['lead', 'crm-deals-kanban', 'hrm'],
            'status' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->organization = Organization::factory()->create([
            'owner_id' => $this->owner->id,
            'plan_id' => $plan->id,
        ]);

        $this->workspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
        ]);

        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach($this->owner);

        \App\Models\UserActiveModule::firstOrCreate(['workspace_id' => $this->workspace->id, 'module_name' => 'lead']);
        \App\Models\UserActiveModule::firstOrCreate(['workspace_id' => $this->workspace->id, 'module_name' => 'crm-deals-kanban']);

        $this->pipeline = CrmPipeline::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Funnel',
            'is_default' => true,
        ]);

        $this->stage = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Qualified',
            'position' => 1,
            'probability' => 20,
        ]);
    }

    public function test_branding_settings_and_update(): void
    {
        Storage::fake('public');

        // 1. View branding settings page
        $res = $this->actingAs($this->owner)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->get('/settings/branding');

        $res->assertStatus(200);
        $res->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Branding')
            ->has('organization')
            ->where('isOrgOwner', true)
        );

        // 2. Update branding with logo and color
        $logo = UploadedFile::fake()->image('brand-logo.png', 100, 100);
        $updateRes = $this->actingAs($this->owner)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->put('/organization/branding', [
                'brand_name' => 'Acme Global Corp',
                'brand_footer_text' => 'Global Enterprise OS',
                'brand_primary_color' => '#10B981',
                'brand_logo' => $logo,
            ]);

        $updateRes->assertRedirect();
        $this->organization->refresh();
        $this->assertEquals('Acme Global Corp', $this->organization->brand_name);
        $this->assertEquals('Global Enterprise OS', $this->organization->brand_footer_text);
        $this->assertEquals('#10B981', $this->organization->brand_primary_color);
        $this->assertNotNull($this->organization->brand_logo_path);

        // 3. Verify HandleInertiaRequests shares branding
        $dashboardRes = $this->actingAs($this->owner)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->get('/dashboard');

        $dashboardRes->assertInertia(fn (Assert $page) => $page
            ->where('tenant.brand_name', 'Acme Global Corp')
            ->where('tenant.brand_primary_color', '#10B981')
        );
    }

    public function test_show_lead_and_convert_to_deal(): void
    {
        $lead = CrmLead::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'name' => 'Global Ventures Inc',
            'company' => 'Global Ventures',
            'estimated_value' => 75000,
            'status' => 'open',
        ]);

        // Show lead API
        $showRes = $this->actingAs($this->owner)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->getJson("/crm/leads/{$lead->id}");

        $showRes->assertStatus(200)
            ->assertJsonPath('lead.name', 'Global Ventures Inc')
            ->assertJsonPath('lead.estimated_value', '75000.00');

        // Convert lead
        $convertRes = $this->actingAs($this->owner)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/leads/{$lead->id}/convert", [
                'name' => 'Global Ventures Enterprise Deal',
                'value' => 75000,
                'create_customer' => true,
            ]);

        $convertRes->assertRedirect();
        $lead->refresh();
        $this->assertEquals('converted', $lead->status);
        $this->assertDatabaseHas('crm_deals', [
            'name' => 'Global Ventures Enterprise Deal',
            'lead_id' => $lead->id,
            'value' => 75000,
        ]);
    }

    public function test_impersonation_lifecycle(): void
    {
        // 1. Super admin impersonates company owner
        $impersonateRes = $this->actingAs($this->superAdmin)
            ->post("/users/{$this->owner->id}/impersonate");

        $impersonateRes->assertRedirect('/dashboard');
        $this->assertEquals($this->superAdmin->id, session('impersonator_id'));

        // 2. Request dashboard as impersonated user -> verify is_impersonating flag in Inertia
        $dashboardRes = $this->actingAs($this->owner)
            ->withSession([
                'impersonator_id' => $this->superAdmin->id,
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->get('/dashboard');

        $dashboardRes->assertInertia(fn (Assert $page) => $page
            ->where('is_impersonating', true)
            ->where('impersonator_name', $this->superAdmin->name)
        );

        // 3. Leave impersonation
        $leaveRes = $this->actingAs($this->owner)
            ->withSession([
                'impersonator_id' => $this->superAdmin->id,
            ])
            ->post('/users/leave-impersonation');

        $leaveRes->assertRedirect('/dashboard');
        $this->assertNull(session('impersonator_id'));
    }
}
