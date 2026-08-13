<?php

namespace App\Http\Controllers\MultiTenancy;

use App\Models\Workspace;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WorkspaceController
{
    public function index(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id');
        $user = $request->user();

        $workspaces = Workspace::where('organization_id', $orgId)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->get();

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id');
        $organization = Organization::findOrFail($orgId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workspace = Workspace::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'created_by' => $request->user()->id,
        ]);

        $request->user()->workspaces()->attach($workspace->id);

        return back()->with('success', 'Workspace created successfully.');
    }

    public function edit(Request $request, int $id)
    {
        $workspace = Workspace::findOrFail($id);
        $this->authorizeWorkspaceAccess($request, $workspace);

        return Inertia::render('Workspaces/Edit', [
            'workspace' => $workspace,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $workspace = Workspace::findOrFail($id);
        $this->authorizeWorkspaceAccess($request, $workspace);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workspace->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect()->route('workspaces.index')->with('success', 'Workspace updated successfully.');
    }

    public function destroy(Request $request, int $id)
    {
        $workspace = Workspace::findOrFail($id);
        $this->authorizeWorkspaceAccess($request, $workspace);

        $org = $workspace->organization;
        if ($org->workspaces()->count() <= 1) {
            return back()->with('error', 'Cannot delete the only workspace in an organization.');
        }

        $workspace->delete();

        return redirect()->route('workspaces.index')->with('success', 'Workspace deleted successfully.');
    }

    public function switchContext(Request $request)
    {
        $request->validate(['workspace_id' => 'required|exists:workspaces,id']);
        $workspace = Workspace::findOrFail($request->workspace_id);

        $user = $request->user();
        $orgId = $request->session()->get('active_organization_id');

        // Security verification: Workspace MUST belong to current active Organization AND User MUST be a member
        if ((int)$workspace->organization_id !== (int)$orgId) {
            abort(403, 'Unauthorized workspace switch: Organization mismatch.');
        }

        $isMember = $user->isSuperAdmin() 
            || (int)$workspace->organization->owner_id === (int)$user->id 
            || $user->workspaces()->where('workspaces.id', $workspace->id)->exists();

        if (!$isMember) {
            abort(403, 'Unauthorized workspace switch: User is not a member.');
        }

        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->put('active_workspace_title', $workspace->name);

        return back()->with('success', 'Switched to workspace: ' . $workspace->name);
    }

    protected function authorizeWorkspaceAccess(Request $request, Workspace $workspace): void
    {
        $orgId = $request->session()->get('active_organization_id');
        if ((int)$workspace->organization_id !== (int)$orgId) {
            abort(403, 'Unauthorized cross-organization workspace access.');
        }

        $user = $request->user();
        $isAuthorized = $user->isSuperAdmin()
            || (int)$workspace->organization->owner_id === (int)$user->id
            || (int)$workspace->created_by === (int)$user->id;

        if (!$isAuthorized) {
            abort(403, 'Unauthorized workspace action.');
        }
    }
}
