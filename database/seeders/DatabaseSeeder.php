<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Workspace Permissions
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'view', 'name' => 'workspace.view'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'create', 'name' => 'workspace.create'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'update', 'name' => 'workspace.update'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'delete', 'name' => 'workspace.delete'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'switch', 'name' => 'workspace.switch'],

            // Member Management Permissions
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'view', 'name' => 'workspace.members.view'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'invite', 'name' => 'workspace.members.invite'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'remove', 'name' => 'workspace.members.remove'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'assign_role', 'name' => 'workspace.members.assign_role'],

            // RBAC Role Management Permissions
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'view', 'name' => 'roles.view'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'create', 'name' => 'roles.create'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'update', 'name' => 'roles.update'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'delete', 'name' => 'roles.delete'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'assign_permissions', 'name' => 'roles.assign_permissions'],

            // User Administration Permissions
            ['module' => 'admin', 'resource' => 'users', 'action' => 'change_password', 'name' => 'users.change_password'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'toggle_status', 'name' => 'users.toggle_status'],

            // Module Runtime Permissions
            ['module' => 'modules', 'resource' => 'modules', 'action' => 'manage', 'name' => 'modules.manage'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        // Seed System Roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'workspace-admin', 'organization_id' => null],
            ['display_name' => 'Workspace Admin', 'is_system' => true]
        );

        $memberRole = Role::firstOrCreate(
            ['name' => 'workspace-member', 'organization_id' => null],
            ['display_name' => 'Workspace Member', 'is_system' => true]
        );

        // Assign all permissions to Workspace Admin
        $allPermissionIds = Permission::pluck('id')->toArray();
        $adminRole->permissions()->sync($allPermissionIds);

        // Assign basic view & switch permissions to Workspace Member
        $memberPermissionIds = Permission::whereIn('name', ['workspace.view', 'workspace.switch', 'workspace.members.view'])->pluck('id')->toArray();
        $memberRole->permissions()->sync($memberPermissionIds);
    }
}
