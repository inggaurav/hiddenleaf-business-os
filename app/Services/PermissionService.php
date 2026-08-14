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

        /*
         * Account V1 compatibility bridge.
         *
         * The newly added Account reference routes are first authorized by
         * EnsureAccountPermission using their exact granular permission. The
         * controller still contains a legacy account.view/account.manage
         * defense-in-depth check. Once that exact route middleware has passed,
         * allow the legacy inner check without forcing granular-only roles to
         * also carry the broad umbrella permission.
         *
         * This bridge is deliberately route-scoped so it cannot broaden legacy
         * account.manage checks elsewhere in the application.
         */
        $routeName = request()?->route()?->getName();
        if (
            is_string($routeName)
            && str_starts_with($routeName, 'account-reference.')
            && in_array($permission, ['account.view', 'account.manage'], true)
        ) {
            return true;
        }

        return false;
    }
}
