<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdversarialTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_a_cannot_access_or_mutate_org_b_or_workspace_b(): void
    {
        // Org A + Workspace A + User A
        $userA = User::factory()->create(['name' => 'User A']);
        $orgA = Organization::factory()->create(['name' => 'Org A', 'owner_id' => $userA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'name' => 'WS A', 'created_by' => $userA->id]);
        $userA->organizations()->attach($orgA->id, ['role' => 'owner']);
        $userA->workspaces()->attach($wsA->id);

        // Org B + Workspace B + User B
        $userB = User::factory()->create(['name' => 'User B']);
        $orgB = Organization::factory()->create(['name' => 'Org B', 'owner_id' => $userB->id]);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'name' => 'WS B', 'created_by' => $userB->id]);
        $userB->organizations()->attach($orgB->id, ['role' => 'owner']);
        $userB->workspaces()->attach($wsB->id);

        $roleB = Role::create(['organization_id' => $orgB->id, 'name' => 'role-b', 'display_name' => 'Role B']);

        // 1. User A sends header X-Organization-ID for Org B -> 403 DENIED
        $res1 = $this->actingAs($userA)
            ->withHeader('X-Organization-ID', (string) $orgB->id)
            ->get('/workspaces');
        $res1->assertStatus(403);

        // 2. User A sends header X-Workspace-ID for Workspace B -> 403 DENIED
        $res2 = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id])
            ->withHeader('X-Workspace-ID', (string) $wsB->id)
            ->get('/workspaces');
        $res2->assertStatus(403);

        // 3. User A attempts HTTP PUT on Workspace B -> 403 DENIED
        $res3 = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id])
            ->put("/workspaces/{$wsB->id}", ['name' => 'Hacked Workspace']);
        $res3->assertStatus(403);

        // 4. User A attempts HTTP DELETE on Workspace B -> 403 DENIED
        $res4 = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id])
            ->delete("/workspaces/{$wsB->id}");
        $res4->assertStatus(403);

        // 5. User A attempts to assign Org B role to member in Workspace A -> 403 DENIED
        $res5 = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id, 'active_workspace_id' => $wsA->id])
            ->post("/members/{$userA->id}/role", ['role_id' => $roleB->id]);
        $res5->assertStatus(403);

        // 6. User A attempts to delete Org B role -> 403 DENIED
        $res6 = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id])
            ->delete("/roles/{$roleB->id}");
        $res6->assertStatus(403);
    }
}
