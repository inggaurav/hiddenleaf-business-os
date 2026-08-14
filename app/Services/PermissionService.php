<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

class PermissionService
{
    public function allows(User $user, Workspace $workspace, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $membership = $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->first();

        if (! $membership) {
            return false;
        }

        $roleId = $membership->pivot->role_id;

        if ($roleId) {
            $role = Role::find($roleId);
            if (! $role || ($role->organization_id && (int) $role->organization_id !== (int) $workspace->organization_id)) {
                return false;
            }
        }

        if ((int) $workspace->organization->owner_id === (int) $user->id) {
            return true;
        }

        if (! $roleId) {
            return false;
        }

        $role = Role::find($roleId);
        if ($role->permissions()->where('name', $permission)->exists()) {
            return true;
        }

        return false;
    }
}
