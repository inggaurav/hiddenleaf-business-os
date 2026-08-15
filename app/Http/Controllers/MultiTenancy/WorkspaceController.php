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
        $organization = Organization::with('plan')->findOrFail($orgId);

        $workspaces = Workspace::where('organization_id', $orgId)
            ->when((int) $organization->owner_id !== (int) $user->id && ! $user->isSuperAdmin(), function ($query) use ($user) {
                $query->whereHas('members', fn ($members) => $members->where('users.id', $user->id));
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
            'workspaceLimit' => $organization->plan?->workspace_limit,
            'canCreate' => $user->isSuperAdmin() || (int) $organization->owner_id === (int) $user->id || ($request->session()->get('active_workspace_id') && $user->canInWorkspace('workspace.create', Workspace::findOrFail($request->session()->get('active_workspace_id')))),
        ]);
    }

    public function store(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id');
        $organization = Organization::with('plan')->findOrFail($orgId);
        $activeWsId = $request->session()->get('active_workspace_id');
        $currentWs = $activeWsId ? Workspace::find($activeWsId) : null;
        $user = $request->user();

        if ($currentWs && (int) $organization->owner_id !== (int) $user->id && ! $user->isSuperAdmin() && ! $user->canInWorkspace('workspace.create', $currentWs)) {
            abort(403, 'Unauthorized to create new workspaces.');
        }

        $limit = (int) ($organization->plan?->workspace_limit ?? 1);
        if (! $user->isSuperAdmin() && $organization->workspaces()->count() >= max(1, $limit)) {
            return back()->with('error', "Your current plan allows {$limit} workspace(s).");
        }

        $validated = $request->validate(['name' => 'required|string|max:255']);
        $workspace = Workspace::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name'].'-'.Str::lower(Str::random(5))),
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $currentMembership = $currentWs ? $user->workspaces()->where('workspaces.id', $currentWs->id)->first() : null;
        $user->workspaces()->syncWithoutDetaching([
            $workspace->id => ['role_id' => $currentMembership?->pivot?->role_id],
        ]);

        return back()->with('success', 'Workspace created successfully.');
    }

    public function edit(Request $request, int $id)
    {
        $workspace = Workspace::findOrFail($id);
        $this->authorizeWorkspaceAction($request, $workspace, 'workspace.update');
        return Inertia::render('Workspaces/Edit', ['workspace' => $workspace]);
    }

    public function update(Request $request, int $id)
    {
        $workspace = Workspace::findOrFail($id);
        $this->authorizeWorkspaceAction($request, $workspace, 'workspace.update');
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $workspace->update(['name' => $validated['name'], 'slug' => Str::slug($validated['name'].'-'.$workspace->id)]);

        if ((int) $request->session()->get('active_workspace_id') === (int) $workspace->id) {
            $request->session()->put('active_workspace_title', $workspace->name);
        }

        return redirect()->route('workspaces.index')->with('success', 'Workspace updated successfully.');
    }

    public function destroy(Request $request, int $id)
    {
        $workspace = Workspace::findOrFail($id);
        $this->authorizeWorkspaceAction($request, $workspace, 'workspace.delete');
        $org = $workspace->organization;
        if ($org->workspaces()->count() <= 1) return back()->with('error', 'Cannot delete the only workspace in an organization.');

        $wasActive = (int) $request->session()->get('active_workspace_id') === (int) $workspace->id;
        $workspace->delete();

        if ($wasActive) {
            $replacement = $org->workspaces()->where('id', '!=', $workspace->id)->first();
            if ($replacement) {
                $request->session()->put('active_workspace_id', $replacement->id);
                $request->session()->put('active_workspace_title', $replacement->name);
                $request->session()->forget('enabled_modules');
            }
        }

        return redirect()->route('workspaces.index')->with('success', 'Workspace deleted successfully.');
    }

    public function switchContext(Request $request)
    {
        $request->validate(['workspace_id' => 'required|exists:workspaces,id']);
        $workspace = Workspace::findOrFail($request->workspace_id);
        $user = $request->user();
        $orgId = $request->session()->get('active_organization_id');

        abort_unless((int) $workspace->organization_id === (int) $orgId, 403, 'Unauthorized workspace switch: Organization mismatch.');
        abort_unless($user->canInWorkspace('workspace.switch', $workspace) || $user->canInWorkspace('workspace.view', $workspace), 403, 'Unauthorized workspace switch: Permission denied.');

        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->put('active_workspace_title', $workspace->name);
        $request->session()->forget('enabled_modules');

        return back()->with('success', 'Switched to workspace: '.$workspace->name);
    }

    protected function authorizeWorkspaceAction(Request $request, Workspace $workspace, string $permission): void
    {
        $orgId = $request->session()->get('active_organization_id');
        abort_unless((int) $workspace->organization_id === (int) $orgId, 403, 'Unauthorized cross-organization workspace access.');

        $organization = $workspace->organization;
        if ((int) $organization->owner_id === (int) $request->user()->id || $request->user()->isSuperAdmin()) return;
        abort_unless($request->user()->canInWorkspace($permission, $workspace), 403, "Unauthorized workspace action: {$permission} required.");
    }
}
