<?php

namespace App\Http\Controllers\MultiTenancy;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Role;
use Illuminate\Http\Request;

class MemberController
{
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $user = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();

        // Validate role belongs to active Organization or system roles
        if (!empty($validated['role_id'])) {
            $role = Role::findOrFail($validated['role_id']);
            if ($role->organization_id && (int)$role->organization_id !== (int)$orgId) {
                abort(403, 'Cross-organization role assignment prohibited.');
            }
        }

        $targetUser = User::firstOrCreate(
            ['email' => $validated['email']],
            ['name' => strstr($validated['email'], '@', true), 'password' => bcrypt(\Illuminate\Support\Str::random(16))]
        );

        // Attach target user to Organization and Workspace
        $targetUser->organizations()->syncWithoutDetaching([$orgId => ['role' => 'member']]);
        $workspace->members()->syncWithoutDetaching([$targetUser->id => ['role_id' => $validated['role_id'] ?? null]]);

        return back()->with('success', 'Member invited to workspace.');
    }

    public function assignRole(Request $request, int $id)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
        $role = Role::findOrFail($validated['role_id']);

        // VERIFY: Role MUST belong to the current Organization (or be system role)
        if ($role->organization_id && (int)$role->organization_id !== (int)$orgId) {
            abort(403, 'Cross-organization role assignment prohibited.');
        }

        $targetUser = User::findOrFail($id);
        $workspace->members()->updateExistingPivot($targetUser->id, ['role_id' => $role->id]);

        return back()->with('success', 'Member role updated.');
    }

    public function remove(Request $request, int $id)
    {
        $user = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
        $targetUser = User::findOrFail($id);

        // Prevent self-lockout or removing organization owner
        if ((int)$user->id === (int)$targetUser->id) {
            return back()->with('error', 'You cannot remove yourself from the workspace.');
        }

        if ((int)$workspace->organization->owner_id === (int)$targetUser->id) {
            return back()->with('error', 'Cannot remove the Organization Owner from the workspace.');
        }

        $workspace->members()->detach($targetUser->id);

        return back()->with('success', 'Member removed from workspace.');
    }
}
