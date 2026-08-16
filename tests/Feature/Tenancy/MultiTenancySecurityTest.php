<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_switch_fails_for_unauthorized_organization_workspace(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Org A']);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'name' => 'WS A']);

        $orgB = Organization::factory()->create(['name' => 'Org B']);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'name' => 'WS B']);

        $userA = User::factory()->create();

        // User A belongs to Org A
        $response = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id])
            ->post('/workspaces/switch', ['workspace_id' => $wsB->id]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_activate_a_workspace_without_existing_tenant_context(): void
    {
        $organization = Organization::factory()->create(['name' => 'Managed Company', 'is_active' => true]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'name' => 'Operations', 'is_active' => true]);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->post('/workspaces/switch', ['workspace_id' => $workspace->id])
            ->assertRedirect()
            ->assertSessionHas('active_organization_id', $organization->id)
            ->assertSessionHas('active_workspace_id', $workspace->id)
            ->assertSessionHas('active_workspace_title', 'Operations');
    }
}
