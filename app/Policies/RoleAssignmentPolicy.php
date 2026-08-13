<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

class RoleAssignmentPolicy
{
    public function canAssign(User $actor, Workspace $workspace, Role $targetRole): bool
    {
        // 1. Super Admin and Organization Owner can assign any valid role in their organization
        if ($actor->isSuperAdmin() || (int) $workspace->organization->owner_id === (int) $actor->id) {
            return true;
        }

        // 2. Role MUST belong to the active Organization or be a global system role
        if ($targetRole->organization_id && (int) $targetRole->organization_id !== (int) $workspace->organization_id) {
            return false;
        }

        // 3. Resolve actor's assigned permissions in this workspace
        $actorMembership = $actor->workspaces()->where('workspaces.id', $workspace->id)->first();
        if (! $actorMembership || ! $actorMembership->pivot->role_id) {
            return false;
        }

        $actorRole = Role::find($actorMembership->pivot->role_id);
        if (! $actorRole) {
            return false;
        }

        $actorPermissionIds = $actorRole->permissions()->pluck('permissions.id')->toArray();
        $targetPermissionIds = $targetRole->permissions()->pluck('permissions.id')->toArray();

        // 4. Privilege Ceiling: Target role permissions MUST be a subset of actor's permissions
        foreach ($targetPermissionIds as $permId) {
            if (! in_array($permId, $actorPermissionIds, true)) {
                return false;
            }
        }

        return true;
    }
}
