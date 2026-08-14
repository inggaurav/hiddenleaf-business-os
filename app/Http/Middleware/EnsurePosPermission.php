<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();
        $permission = $this->permissionForRoute($routeName);

        if ($permission === null) {
            return $next($request);
        }

        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace, 403, 'Active workspace context required.');

        $user = $request->user();
        abort_unless($user, 401);

        abort_unless(
            $user->canInWorkspace($permission, $workspace)
                || $user->canInWorkspace('pos.manage', $workspace),
            403,
            'Permission denied for '.$permission
        );

        return $next($request);
    }

    private function permissionForRoute(string $routeName): ?string
    {
        return match (true) {
            $routeName === 'pos.index', $routeName === 'pos' => 'pos.dashboard.view',
            $routeName === 'pos.orders', $routeName === 'pos.show' => 'pos.order.view',
            $routeName === 'pos.create', $routeName === 'pos.terminal', $routeName === 'pos.products', $routeName === 'pos.pos-number' => 'pos.order.create',
            $routeName === 'pos.store' => 'pos.checkout.execute',
            $routeName === 'pos-orders.print' => 'pos.order.print',
            $routeName === 'pos.barcode' => 'pos.barcode.view',
            $routeName === 'pos.barcode.print' => 'pos.barcode.print',

            $routeName === 'pos.billing-counters' => 'pos.counter.view',
            $routeName === 'pos.billing-counters.store' => 'pos.counter.create',
            $routeName === 'pos.billing-counters.update' => 'pos.counter.update',
            $routeName === 'pos.billing-counters.destroy' => 'pos.counter.delete',

            $routeName === 'pos.discounts.index', $routeName === 'pos.discounts.show' => 'pos.discount.view',
            $routeName === 'pos.discounts.create', $routeName === 'pos.discounts.store' => 'pos.discount.create',
            $routeName === 'pos.discounts.edit', $routeName === 'pos.discounts.update' => 'pos.discount.update',
            $routeName === 'pos.discounts.destroy' => 'pos.discount.delete',

            str_starts_with($routeName, 'pos.reports.') => 'pos.report.view',

            $routeName === 'pos.returns.index', $routeName === 'pos.returns.show' => 'pos.return.view',
            $routeName === 'pos.returns.create', $routeName === 'pos.returns.store' => 'pos.return.create',
            $routeName === 'pos.returns.approve' => 'pos.return.approve',
            $routeName === 'pos.returns.complete' => 'pos.return.complete',
            $routeName === 'pos.returns.destroy' => 'pos.return.delete',

            default => null,
        };
    }
}
