<?php

namespace App\Http\Controllers\MultiTenancy;

use App\Models\Organization;
use App\Models\Workspace;
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

        $activeWsId = $request->session()->get('active_workspace_id');
        $currentWs = Workspace::find($activeWsId);

        if ($currentWs && ! $request->user()->canInWorkspace('workspace.create', $currentWs)) {
            abort(403, 'Unauthorized to create new workspaces.');
        }

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
        $this->authorizeWorkspaceAction($request, $workspace, 'workspace.update');

        return Inertia::render('Workspaces/Edit', [
            'workspace' => $workspace,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $workspace = Workspace::findOrFail($id);
        $this->authorizeWorkspaceAction($request, $workspace, 'workspace.update');

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
        $this->authorizeWorkspaceAction($request, $workspace, 'workspace.delete');

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

        if ((int) $workspace->organization_id !== (int) $orgId) {
            abort(403, 'Unauthorized workspace switch: Organization mismatch.');
        }

        if (! $user->canInWorkspace('workspace.switch', $workspace) && ! $user->canInWorkspace('workspace.view', $workspace)) {
            abort(403, 'Unauthorized workspace switch: Permission denied.');
        }

        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->put('active_workspace_title', $workspace->name);

        return back()->with('success', 'Switched to workspace: '.$workspace->name);
    }

    protected function authorizeWorkspaceAction(Request $request, Workspace $workspace, string $permission): void
    {
        $orgId = $request->session()->get('active_organization_id');
        if ((int) $workspace->organization_id !== (int) $orgId) {
            abort(403, 'Unauthorized cross-organization workspace access.');
        }

        if (! $request->user()->canInWorkspace($permission, $workspace)) {
            abort(403, "Unauthorized workspace action: {$permission} required.");
        }
    }
}
