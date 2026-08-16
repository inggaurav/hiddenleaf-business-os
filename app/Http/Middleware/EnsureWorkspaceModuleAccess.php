<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\AddonManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceModuleAccess
{
    public function __construct(private readonly AddonManager $addons) {}

    /** @var array<string, string> */
    private const PREFIX_MODULES = [
        'sales' => 'sales',
        'sales-invoices' => 'sales',
        'sales-proposals' => 'sales',
        'sales-returns' => 'sales',
        'procurement' => 'procurement',
        'purchase-invoices' => 'procurement',
        'purchase-invoices-dashboard' => 'procurement',
        'purchase-returns' => 'procurement',
        'inbox' => 'communications',
        'automations' => 'automations',
        'missions' => 'missions',
        'command-center' => 'command_center',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $module = $this->moduleForPath($request->path());
        if (! $module) {
            return $next($request);
        }

        $workspaceId = (int) $request->session()->get('active_workspace_id');
        $organizationId = (int) $request->session()->get('active_organization_id');
        abort_unless($workspaceId > 0 && $organizationId > 0, 403, 'No active workspace.');

        $workspace = Workspace::query()
            ->with('organization')
            ->where('organization_id', $organizationId)
            ->find($workspaceId);
        abort_unless($workspace, 403, 'Invalid active workspace.');

        abort_unless(
            $this->addons->canUse($workspace, $module),
            403,
            "Module {$module} is not active for this workspace or included in its plan."
        );

        return $next($request);
    }

    private function moduleForPath(string $path): ?string
    {
        $first = explode('/', trim($path, '/'))[0] ?? '';

        return self::PREFIX_MODULES[$first] ?? null;
    }
}
