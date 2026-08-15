<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\AddonManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleStatus
{
    public function __construct(private readonly AddonManager $addons) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        $workspaceId = $request->session()->get('active_workspace_id');
        if (! $workspaceId) { abort(403, 'No active workspace'); }

        $workspace = Workspace::query()->with('organization')->find($workspaceId);
        if (! $workspace) { abort(403, 'Invalid active workspace.'); }

        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        if (! $this->addons->canUse($workspace, $moduleName, $isSuperAdmin)) {
            abort(403, "Module {$moduleName} is not active for this workspace or included in its plan.");
        }

        return $next($request);
    }
}
