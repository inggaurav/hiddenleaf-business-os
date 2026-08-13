<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_workspace_administrator_can_toggle_module_activation()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id]);
        $user->organizations()->attach($org);
        $user->workspaces()->attach($workspace);

        $this->actingAs($user)->withSession([
            'active_workspace_id' => $workspace->id,
            'active_organization_id' => $org->id,
        ]);

        // Enable
        $response = $this->post(route('modules.toggle'), [
            'module_name' => 'HR',
            'active' => true,
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);

        $this->assertDatabaseHas('user_active_modules', [
            'workspace_id' => $workspace->id,
            'module_name' => 'HR',
        ]);

        // Disable
        $response = $this->post(route('modules.toggle'), [
            'module_name' => 'HR',
            'active' => false,
        ]);
        $response->assertStatus(302);
        $this->assertDatabaseMissing('user_active_modules', [
            'workspace_id' => $workspace->id,
            'module_name' => 'HR',
        ]);
    }

    public function test_ordinary_workspace_member_cannot_toggle_module_activation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id]);
        $member->organizations()->attach($org, ['role' => 'member']);
        $member->workspaces()->attach($workspace);

        $this->actingAs($member)
            ->withSession([
                'active_workspace_id' => $workspace->id,
                'active_organization_id' => $org->id,
            ])
            ->post(route('modules.toggle'), [
                'module_name' => 'account',
                'active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('user_active_modules', [
            'workspace_id' => $workspace->id,
            'module_name' => 'account',
        ]);
    }

    public function test_member_with_manage_modules_permission_can_toggle_module_activation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id]);
        $permission = Permission::create([
            'module' => 'modules',
            'resource' => 'modules',
            'action' => 'manage',
            'name' => 'modules.manage',
        ]);
        $role = Role::create([
            'organization_id' => $org->id,
            'name' => 'module-manager',
            'display_name' => 'Module Manager',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission);
        $member->organizations()->attach($org, ['role' => 'member']);
        $member->workspaces()->attach($workspace, ['role_id' => $role->id]);

        $this->assertTrue($member->fresh()->canInWorkspace('modules.manage', $workspace->fresh()));

        $this->actingAs($member)
            ->withSession([
                'active_workspace_id' => $workspace->id,
                'active_organization_id' => $org->id,
            ])
            ->post(route('modules.toggle'), [
                'module_name' => 'account',
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_active_modules', [
            'workspace_id' => $workspace->id,
            'module_name' => 'account',
        ]);
    }

    public function test_cross_organization_role_cannot_authorize_module_mutation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $otherOrg = Organization::factory()->create();
        $workspace = Workspace::factory()->create(['organization_id' => $org->id]);
        $permission = Permission::create([
            'module' => 'modules',
            'resource' => 'modules',
            'action' => 'manage',
            'name' => 'modules.manage',
        ]);
        $foreignRole = Role::create([
            'organization_id' => $otherOrg->id,
            'name' => 'foreign-module-manager',
            'display_name' => 'Foreign Module Manager',
            'is_system' => false,
        ]);
        $foreignRole->permissions()->attach($permission);
        $member->organizations()->attach($org, ['role' => 'member']);
        $member->workspaces()->attach($workspace, ['role_id' => $foreignRole->id]);

        $this->actingAs($member)
            ->withSession([
                'active_workspace_id' => $workspace->id,
                'active_organization_id' => $org->id,
            ])
            ->post(route('modules.toggle'), [
                'module_name' => 'account',
                'active' => true,
            ])
            ->assertForbidden();
    }
}
