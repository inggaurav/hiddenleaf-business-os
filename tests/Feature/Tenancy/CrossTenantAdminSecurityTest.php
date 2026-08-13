<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantAdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_org_a_company_admin_cannot_change_password_or_toggle_status_of_org_b_user(): void
    {
        // Org A Admin + User
        $adminA = User::factory()->create(['name' => 'Admin A', 'role' => 'company_admin']);
        $orgA = Organization::factory()->create(['name' => 'Org A', 'owner_id' => $adminA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $adminA->id]);
        $adminA->organizations()->attach($orgA->id, ['role' => 'owner']);
        $adminA->workspaces()->attach($wsA->id);

        // Org B User
        $userB = User::factory()->create(['name' => 'User B', 'role' => 'member']);
        $orgB = Organization::factory()->create(['name' => 'Org B', 'owner_id' => $userB->id]);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'created_by' => $userB->id]);
        $userB->organizations()->attach($orgB->id, ['role' => 'owner']);
        $userB->workspaces()->attach($wsB->id);

        // 1. Admin A attempts to change password of User B in Org B -> 403 DENIED
        $res1 = $this->actingAs($adminA)
            ->withSession(['active_organization_id' => $orgA->id, 'active_workspace_id' => $wsA->id])
            ->post("/users/{$userB->id}/change-password", [
                'password' => 'HackedPassword123!',
                'password_confirmation' => 'HackedPassword123!',
            ]);
        $res1->assertStatus(403);

        // 2. Admin A attempts to toggle active status of User B in Org B -> 403 DENIED
        $res2 = $this->actingAs($adminA)
            ->withSession(['active_organization_id' => $orgA->id, 'active_workspace_id' => $wsA->id])
            ->patch("/users/{$userB->id}/toggle-status");
        $res2->assertStatus(403);
    }
}
