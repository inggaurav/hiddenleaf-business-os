<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        $workspaceId = $request->user()?->current_workspace_id;
        if (!$workspaceId) {
            abort(403, 'No active workspace');
        }

        $isActive = \App\Models\UserActiveModule::where('workspace_id', $workspaceId)
            ->where('module_name', $moduleName)
            ->exists();

        if (!$isActive) {
            abort(403, "Module {$moduleName} is not active for this workspace.");
        }

        return $next($request);
    }
}
