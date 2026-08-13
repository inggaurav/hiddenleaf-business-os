<?php

namespace App\Http\Controllers\MultiTenancy;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\RoleAssignmentPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberController
{
    protected RoleAssignmentPolicy $roleAssignmentPolicy;

    public function __construct(RoleAssignmentPolicy $roleAssignmentPolicy)
    {
        $this->roleAssignmentPolicy = $roleAssignmentPolicy;
    }

    public function invite(Request $request)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();

        if (! $actor->canInWorkspace('workspace.members.invite', $workspace)) {
            abort(403, 'Unauthorized to invite workspace members.');
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $assignedRoleId = null;

        if (! empty($validated['role_id'])) {
            if (! $actor->canInWorkspace('workspace.members.assign_role', $workspace)) {
                abort(403, 'Unauthorized to specify role during member invitation without assign_role permission.');
            }

            $requestedRole = Role::findOrFail($validated['role_id']);

            if (! $this->roleAssignmentPolicy->canAssign($actor, $workspace, $requestedRole)) {
                abort(403, 'Privilege escalation attempt: Cannot assign role with higher permissions than your own.');
            }

            $assignedRoleId = $requestedRole->id;
        } else {
            $defaultMemberRole = Role::where('name', 'workspace-member')->first();
            $assignedRoleId = $defaultMemberRole ? $defaultMemberRole->id : null;
        }

        $targetUser = User::firstOrCreate(
            ['email' => $validated['email']],
            ['name' => strstr($validated['email'], '@', true), 'password' => bcrypt(Str::random(16))]
        );

        $targetUser->organizations()->syncWithoutDetaching([$orgId => ['role' => 'member']]);
        $workspace->members()->syncWithoutDetaching([$targetUser->id => ['role_id' => $assignedRoleId]]);

        return back()->with('success', 'Member invited to workspace.');
    }

    public function assignRole(Request $request, int $id)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();

        if (! $actor->canInWorkspace('workspace.members.assign_role', $workspace)) {
            abort(403, 'Unauthorized to assign member roles.');
        }

        $targetUser = $workspace->members()->where('users.id', $id)->firstOrFail();

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if (! $this->roleAssignmentPolicy->canAssign($actor, $workspace, $role)) {
            abort(403, 'Privilege escalation attempt: Cannot assign role with higher permissions than your own.');
        }

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

        if (! $actor->canInWorkspace('workspace.members.remove', $workspace)) {
            abort(403, 'Unauthorized to remove workspace members.');
        }

        $targetUser = $workspace->members()->where('users.id', $id)->firstOrFail();

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
