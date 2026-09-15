<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureTenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_without_org_membership_can_access_module_route()
    {
        $plan = Plan::create([
            'name' => 'Demo',
            'modules' => ['hrm'],
            'price_per_user_monthly' => 0,
            'price_per_user_yearly' => 0,
            'price_per_storage_monthly' => 0,
            'price_per_storage_yearly' => 0,
            'number_of_users' => 10,
            'storage_limit' => 10,
            'workspace_limit' => 1,
            'status' => 1,
            'created_by' => 1,
        ]);
        
        $orgOwner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $org = Organization::create([
            'name' => 'Fallback Org',
            'slug' => 'fallback-org',
            'owner_id' => $orgOwner->id,
            'plan_id' => $plan->id,
            'is_active' => 1,
        ]);

        $workspace = Workspace::create([
            'organization_id' => $org->id,
            'name' => 'Fallback Workspace',
            'slug' => 'fallback-workspace',
            'created_by' => $orgOwner->id,
            'is_active' => 1,
        ]);

        \App\Models\UserActiveModule::create([
            'workspace_id' => $workspace->id,
            'module_name' => 'hrm',
            'is_active' => 1,
        ]);

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'type' => 'super_admin',
        ]);

        // The super admin doesn't belong to any organization.
        $response = $this->actingAs($superAdmin)->get('/hrm');
        
        // Should not be 403 or redirect to /login with "Please join or create an organization"
        $response->assertStatus(200);

        // Ensure session was populated with the fallback context
        $this->assertEquals($workspace->id, session('active_workspace_id'));
        $this->assertEquals($org->id, session('active_organization_id'));
    }
}
