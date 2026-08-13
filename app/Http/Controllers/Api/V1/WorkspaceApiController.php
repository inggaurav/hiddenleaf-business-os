<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workspaces = $user->isSuperAdmin() ? Workspace::all() : $user->workspaces;

        return response()->json([
            'success' => true,
            'workspaces' => $workspaces,
        ]);
    }

    public function show(Request $request, Workspace $workspace)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized workspace.'], 403);
        }

        return response()->json([
            'success' => true,
            'workspace' => $workspace,
        ]);
    }
}
