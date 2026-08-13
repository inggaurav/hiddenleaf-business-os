<?php

namespace Tests\Feature\RBAC;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_full_access(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->assertTrue($admin->isSuperAdmin());
    }

    public function test_roles_can_be_assigned_permissions(): void
    {
        $role = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $perm = Permission::create([
            'module' => 'workspace',
            'resource' => 'members',
            'action' => 'invite',
            'name' => 'workspace.members.invite',
        ]);

        $role->permissions()->attach($perm->id);
        $this->assertCount(1, $role->permissions);
        $this->assertEquals('workspace.members.invite', $role->permissions->first()->name);
    }
}
