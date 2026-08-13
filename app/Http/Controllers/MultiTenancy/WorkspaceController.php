<?php

namespace App\Http\Controllers\MultiTenancy;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController
{
    public function index(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id', 1);
        $workspaces = Workspace::where('organization_id', $orgId)->get();

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id', 1);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workspace = Workspace::create([
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
            'created_by' => $request->user()->id,
        ]);

        $request->user()->workspaces()->attach($workspace->id);

        return back()->with('success', 'Workspace created successfully.');
    }

    public function switchContext(Request $request)
    {
        $request->validate(['workspace_id' => 'required|exists:workspaces,id']);
        $workspace = Workspace::findOrFail($request->workspace_id);

        $orgId = $request->session()->get('active_organization_id', 1);
        if ((int)$workspace->organization_id !== (int)$orgId) {
            abort(403, 'Unauthorized workspace switch attempt.');
        }

        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->put('active_workspace_title', $workspace->name);

        return back()->with('success', 'Switched to workspace: ' . $workspace->name);
    }
}
