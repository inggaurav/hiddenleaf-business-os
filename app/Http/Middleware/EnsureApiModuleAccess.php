<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\AddonManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiModuleAccess
{
    public function __construct(private readonly AddonManager $addons) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        $workspace = $request->attributes->get('workspace');
        if (! $workspace instanceof Workspace) {
            return response()->json(['message' => 'Workspace context is required before module authorization.'], 403);
        }

        if (! $this->addons->canUse($workspace, strtolower($module))) {
            return response()->json([
                'message' => 'This module is not active for the workspace or included in its plan.',
                'module' => strtolower($module),
            ], 403);
        }

        return $next($request);
    }
}
