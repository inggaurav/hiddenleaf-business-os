<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workspaces = $user->isSuperAdmin()
            ? Workspace::query()->orderBy('name')->paginate(min((int) $request->input('per_page', 20), 100))
            : $user->workspaces()->orderBy('name')->paginate(min((int) $request->input('per_page', 20), 100));

        return WorkspaceResource::collection($workspaces)->additional(['success' => true]);
    }

    public function show(Request $request, Workspace $workspace)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized workspace.'], 403);
        }

        return (new WorkspaceResource($workspace))->additional(['success' => true]);
    }
}
