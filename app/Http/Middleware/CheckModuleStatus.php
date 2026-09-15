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
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $workspaceId = $request->session()->get('active_workspace_id');
        if (! $workspaceId) {
            abort(403, 'No active workspace');
        }

        $workspace = Workspace::query()->with('organization')->find($workspaceId);
        if (! $workspace) {
            abort(403, 'Invalid active workspace.');
        }

        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        $moduleList = [];
        foreach ($modules as $mod) {
            foreach (preg_split('/[|,]/', $mod) as $item) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $moduleList[] = $trimmed;
                }
            }
        }

        $hasAccess = false;
        foreach ($moduleList as $mod) {
            if ($this->addons->canUse($workspace, $mod, $isSuperAdmin)) {
                $hasAccess = true;
                break;
            }
        }

        if (! $hasAccess) {
            $names = implode(', ', $moduleList);
            abort(403, "Module {$names} is not active for this workspace or included in its plan.");
        }

        return $next($request);
    }
}
