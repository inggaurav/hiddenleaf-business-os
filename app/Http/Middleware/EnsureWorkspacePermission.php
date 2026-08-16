<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspacePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $workspace = Workspace::find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user() && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $next($request);
    }
}
