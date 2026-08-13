<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $request->header('X-Workspace-ID');

        if (! is_string($workspaceId) || ! ctype_digit($workspaceId)) {
            return response()->json([
                'message' => 'The X-Workspace-ID header is required.',
                'errors' => ['workspace' => ['A valid workspace context is required.']],
            ], 422);
        }

        $workspace = Workspace::query()->with('organization')->find($workspaceId);
        $user = $request->user();

        if (! $workspace || (! $user->isSuperAdmin() && ! $user->workspaces()->whereKey($workspace->id)->exists())) {
            return response()->json(['message' => 'Workspace not found.'], 404);
        }

        if (! $workspace->is_active || ! $workspace->organization?->is_active) {
            return response()->json(['message' => 'Workspace is inactive.'], 403);
        }

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
