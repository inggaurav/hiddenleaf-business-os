<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_inviting_with_role_id_without_assign_role_permission_is_denied(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $owner->id]);

        $inviterRole = Role::create(['organization_id' => $org->id, 'name' => 'inviter-only', 'display_name' => 'Inviter Only']);
        $invitePerm = Permission::where('name', 'workspace.members.invite')->first();
        $inviterRole->permissions()->attach($invitePerm->id);

        $inviterUser = User::factory()->create(['name' => 'Inviter User']);
        $org->members()->attach($inviterUser->id, ['role' => 'member']);
        $ws->members()->attach($inviterUser->id, ['role_id' => $inviterRole->id]);

        $adminRole = Role::where('name', 'workspace-admin')->first();

        // Inviter with ONLY invite permission tries specifying workspace-admin role_id -> 403 DENIED
        $res = $this->actingAs($inviterUser)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post('/members/invite', [
                'email' => 'hackedadmin@hiddenleaf.io',
                'role_id' => $adminRole->id,
            ]);

        $res->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'hackedadmin@hiddenleaf.io']);
    }

    public function test_role_assignment_privilege_ceiling_prevents_escalation(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $owner->id]);

        $managerRole = Role::create(['organization_id' => $org->id, 'name' => 'manager', 'display_name' => 'Manager']);
        $assignPerm = Permission::where('name', 'workspace.members.assign_role')->first();
        $managerRole->permissions()->attach($assignPerm->id);

        $managerUser = User::factory()->create(['name' => 'Manager User']);
        $org->members()->attach($managerUser->id, ['role' => 'member']);
        $ws->members()->attach($managerUser->id, ['role_id' => $managerRole->id]);

        $targetMember = User::factory()->create(['name' => 'Target Member']);
        $org->members()->attach($targetMember->id, ['role' => 'member']);
        $ws->members()->attach($targetMember->id);

        $adminRole = Role::where('name', 'workspace-admin')->first();

        // Manager (with fewer permissions) tries assigning workspace-admin role -> 403 DENIED
        $res = $this->actingAs($managerUser)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post("/members/{$targetMember->id}/role", [
                'role_id' => $adminRole->id,
            ]);

        $res->assertStatus(403);
    }

    public function test_system_roles_cannot_be_mutated_by_tenant_admin(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $owner->id]);

        $systemRole = Role::where('name', 'workspace-admin')->first();

        // Tenant Admin attempts to update system role display_name -> 403 DENIED
        $res = $this->actingAs($owner)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->put("/roles/{$systemRole->id}", [
                'display_name' => 'Hacked System Role',
            ]);

        $res->assertStatus(403);
    }

    public function test_poisoned_membership_pivot_with_foreign_org_role_denies_permission(): void
    {
        $userA = User::factory()->create(['name' => 'User A']);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $userA->id]);

        $orgB = Organization::factory()->create();
        $foreignRole = Role::create(['organization_id' => $orgB->id, 'name' => 'foreign-role', 'display_name' => 'Foreign Role']);
        $invitePerm = Permission::where('name', 'workspace.members.invite')->first();
        $foreignRole->permissions()->attach($invitePerm->id);

        // Attach user to workspace A using poisoned foreign role from Org B
        $wsA->members()->attach($userA->id, ['role_id' => $foreignRole->id]);

        // PermissionService MUST deny permission due to cross-organization role mismatch
        $allowed = $userA->canInWorkspace('workspace.members.invite', $wsA);
        $this->assertFalse($allowed);
    }
}
