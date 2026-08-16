<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Workspace;
use Illuminate\Http\Request;

trait AuthorizesSettings
{
    private function settingsWorkspace(Request $request, string $permission): ?Workspace
    {
        if ($request->user()->isSuperAdmin()) {
            return null;
        }

        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }
}
