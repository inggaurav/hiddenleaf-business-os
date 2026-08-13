<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        $workspaceId = $request->session()->get('active_workspace_id');
        if (! $workspaceId) {
            abort(403, 'No active workspace');
        }

        $workspace = Workspace::query()->with('organization')->find($workspaceId);
        if (! $workspace) {
            abort(403, 'Invalid active workspace.');
        }

        $alias = strtolower($moduleName);

        $isActive = UserActiveModule::where('workspace_id', $workspaceId)
            ->whereRaw('LOWER(module_name) = ?', [$alias])
            ->exists();

        if (! $isActive) {
            abort(403, "Module {$moduleName} is not active for this workspace.");
        }

        if (! $request->user()->isSuperAdmin()) {
            $plan = $workspace->organization->plan_id ? Plan::find($workspace->organization->plan_id) : null;
            if (! $plan || ! in_array($alias, array_map('strtolower', $plan->modules ?? []), true)) {
                abort(403, "Module {$moduleName} is not included in the organization's plan.");
            }
        }

        return $next($request);
    }
}
