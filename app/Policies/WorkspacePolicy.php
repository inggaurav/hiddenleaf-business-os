<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $user->isSuperAdmin() 
            || (int) $workspace->organization->owner_id === (int) $user->id
            || $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->role === 'company_admin' || $user->organizations()->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->isSuperAdmin()
            || (int) $workspace->organization->owner_id === (int) $user->id
            || (int) $workspace->created_by === (int) $user->id;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        // Primary operations workspace cannot be deleted if it's the only one
        if ($workspace->organization->workspaces()->count() <= 1) {
            return false;
        }

        return $user->isSuperAdmin() || (int) $workspace->organization->owner_id === (int) $user->id;
    }
}
