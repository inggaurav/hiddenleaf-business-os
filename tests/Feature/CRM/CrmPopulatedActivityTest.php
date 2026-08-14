<?php

namespace Tests\Feature\CRM;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmPopulatedActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_dashboard_renders_with_populated_activities(): void
    {
        $this->seed();

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'CRM Plan', 'status' => true, 'modules' => ['lead'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        UserActiveModule::create(['workspace_id' => $ws->id, 'module_name' => 'lead']);

        DB::table('crm_activities')->insert([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'title' => 'Call client regarding enterprise deal',
            'type' => 'call',
            'due_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_activities')->insert([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'title' => 'Send contract follow-up',
            'type' => 'email',
            'due_at' => now()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'active_organization_id' => $org->id,
                'active_workspace_id' => $ws->id,
            ])
            ->get('/crm/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('CRM/Dashboard')
            ->has('recentActivities', 2)
        );
    }
}
