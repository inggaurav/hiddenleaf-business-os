<?php

namespace App\Http\Controllers\MultiTenancy;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberController
{
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $wsId = $request->session()->get('active_workspace_id', 1);
        $workspace = Workspace::findOrFail($wsId);

        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            ['name' => strstr($validated['email'], '@', true), 'password' => bcrypt(\Illuminate\Support\Str::random(16))]
        );

        $workspace->members()->syncWithoutDetaching([$user->id => ['role_id' => $validated['role_id'] ?? null]]);

        return back()->with('success', 'Member invited to workspace.');
    }

    public function remove(Request $request, int $id)
    {
        $wsId = $request->session()->get('active_workspace_id', 1);
        $workspace = Workspace::findOrFail($wsId);
        $workspace->members()->detach($id);

        return back()->with('success', 'Member removed from workspace.');
    }
}
