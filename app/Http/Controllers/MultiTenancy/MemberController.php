<?php

namespace App\Http\Controllers\MultiTenancy;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberController
{
    public function invite(Request $request)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();

        // ENFORCE RBAC: Requires workspace.members.invite
        if (! $actor->canInWorkspace('workspace.members.invite', $workspace)) {
            abort(403, 'Unauthorized to invite workspace members.');
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        if (! empty($validated['role_id'])) {
            $role = Role::findOrFail($validated['role_id']);
            if ($role->organization_id && (int) $role->organization_id !== (int) $orgId) {
                abort(403, 'Cross-organization role assignment prohibited.');
            }
        }

        $targetUser = User::firstOrCreate(
            ['email' => $validated['email']],
            ['name' => strstr($validated['email'], '@', true), 'password' => bcrypt(Str::random(16))]
        );

        $targetUser->organizations()->syncWithoutDetaching([$orgId => ['role' => 'member']]);
        $workspace->members()->syncWithoutDetaching([$targetUser->id => ['role_id' => $validated['role_id'] ?? null]]);

        return back()->with('success', 'Member invited to workspace.');
    }

    public function assignRole(Request $request, int $id)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();

        // ENFORCE RBAC: Requires workspace.members.assign_role
        if (! $actor->canInWorkspace('workspace.members.assign_role', $workspace)) {
            abort(403, 'Unauthorized to assign member roles.');
        }

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if ($role->organization_id && (int) $role->organization_id !== (int) $orgId) {
            abort(403, 'Cross-organization role assignment prohibited.');
        }

        $targetUser = User::findOrFail($id);

        // Prevent modifying organization owner role if actor is not owner/super admin
        if ((int) $workspace->organization->owner_id === (int) $targetUser->id && (int) $actor->id !== (int) $targetUser->id && ! $actor->isSuperAdmin()) {
            abort(403, 'Cannot mutate Organization Owner role.');
        }

        $workspace->members()->updateExistingPivot($targetUser->id, ['role_id' => $role->id]);

        return back()->with('success', 'Member role updated.');
    }

    public function remove(Request $request, int $id)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();

        // ENFORCE RBAC: Requires workspace.members.remove
        if (! $actor->canInWorkspace('workspace.members.remove', $workspace)) {
            abort(403, 'Unauthorized to remove workspace members.');
        }

        $targetUser = User::findOrFail($id);

        if ((int) $actor->id === (int) $targetUser->id) {
            return back()->with('error', 'You cannot remove yourself from the workspace.');
        }

        if ((int) $workspace->organization->owner_id === (int) $targetUser->id) {
            return back()->with('error', 'Cannot remove the Organization Owner from the workspace.');
        }

        $workspace->members()->detach($targetUser->id);

        return back()->with('success', 'Member removed from workspace.');
    }
}
