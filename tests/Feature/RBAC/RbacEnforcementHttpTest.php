<?php

namespace Tests\Feature\RBAC;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacEnforcementHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_normal_member_without_permissions_is_denied_member_and_role_management(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $owner->id]);

        $memberRole = Role::where('name', 'workspace-member')->first();
        $normalMember = User::factory()->create(['name' => 'Normal Member']);

        $org->members()->attach($normalMember->id, ['role' => 'member']);
        $ws->members()->attach($normalMember->id, ['role_id' => $memberRole->id]);

        // 1. Member attempts to invite user -> 403 DENIED
        $res1 = $this->actingAs($normalMember)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post('/members/invite', ['email' => 'newuser@hiddenleaf.io']);
        $res1->assertStatus(403);

        // 2. Member attempts to assign role -> 403 DENIED
        $res2 = $this->actingAs($normalMember)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post("/members/{$owner->id}/role", ['role_id' => $memberRole->id]);
        $res2->assertStatus(403);

        // 3. Member attempts to remove member -> 403 DENIED
        $res3 = $this->actingAs($normalMember)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->delete("/members/{$owner->id}");
        $res3->assertStatus(403);

        // 4. Member attempts to create role -> 403 DENIED
        $res4 = $this->actingAs($normalMember)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post('/roles', ['name' => 'hacker', 'display_name' => 'Hacker']);
        $res4->assertStatus(403);
    }

    public function test_granting_permission_allows_action_and_revoking_it_denies_action(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $owner->id]);

        $customRole = Role::create(['organization_id' => $org->id, 'name' => 'custom-inviter', 'display_name' => 'Custom Inviter']);
        $invitePerm = Permission::where('name', 'workspace.members.invite')->first();

        $customRole->permissions()->attach($invitePerm->id);

        $testUser = User::factory()->create(['name' => 'Test User']);
        $org->members()->attach($testUser->id, ['role' => 'member']);
        $ws->members()->attach($testUser->id, ['role_id' => $customRole->id]);

        // With workspace.members.invite permission -> 302 ALLOWED
        $res1 = $this->actingAs($testUser)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post('/members/invite', ['email' => 'guest@hiddenleaf.io']);
        $res1->assertStatus(302);

        // Revoke workspace.members.invite permission
        $customRole->permissions()->detach($invitePerm->id);

        // Without workspace.members.invite permission -> 403 DENIED
        $res2 = $this->actingAs($testUser)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post('/members/invite', ['email' => 'guest2@hiddenleaf.io']);
        $res2->assertStatus(403);
    }
}
