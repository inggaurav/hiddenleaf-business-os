<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

class PermissionService
{
    public function allows(User $user, Workspace $workspace, string $permission): bool
    {
        // 1. Super Admin bypasses all checks
        if ($user->isSuperAdmin()) {
            return true;
        }

        // 2. Organization Owner has full management privileges in their organization
        if ((int) $workspace->organization->owner_id === (int) $user->id) {
            return true;
        }

        // 3. Verify user belongs to the workspace
        $membership = $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->first();

        if (! $membership) {
            return false;
        }

        $roleId = $membership->pivot->role_id;
        if (! $roleId) {
            return false;
        }

        // 4. Defense-in-depth: Role MUST be a global system role OR belong to workspace's organization
        $role = Role::find($roleId);
        if (! $role) {
            return false;
        }

        if ($role->organization_id && (int) $role->organization_id !== (int) $workspace->organization_id) {
            // Poisoned membership pivot with cross-organization role -> DENY
            return false;
        }

        // 5. Resolve assigned permissions for the member's role
        return $role->permissions()
            ->where('name', $permission)
            ->exists();
    }
}
