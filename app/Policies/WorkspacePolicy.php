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
        return $user->canInWorkspace('workspace.view', $workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->canInWorkspace('workspace.create', $workspace);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->canInWorkspace('workspace.update', $workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        if ($workspace->organization->workspaces()->count() <= 1) {
            return false;
        }

        return $user->canInWorkspace('workspace.delete', $workspace);
    }
}
