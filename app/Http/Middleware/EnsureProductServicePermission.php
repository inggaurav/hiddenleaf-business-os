<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProductServicePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();
        $permission = $this->permissionForRoute($routeName);

        // Not a product-service route — let it pass without any workspace check
        if ($permission === null) {
            return $next($request);
        }

        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace, 403, 'Active workspace context required.');

        $user = $request->user();
        abort_unless($user, 401);

        // Map granular permission to legacy umbrella permission for backward compatibility
        $legacy = str_starts_with($permission, 'inventory.') ? 'inventory.manage' : 'product_service.manage';

        abort_unless(
            $user->canInWorkspace($permission, $workspace)
                || $user->canInWorkspace($legacy, $workspace)
                || ($permission === 'inventory.stock.adjust' && $user->canInWorkspace('inventory.adjust', $workspace)),
            403,
            'Permission denied for '.$permission
        );

        return $next($request);
    }

    private function permissionForRoute(string $routeName): ?string
    {
        return match (true) {
            $routeName === 'product-service.dashboard', $routeName === 'inventory.dashboard' => 'inventory.stock.view',
            $routeName === 'product-service.index', $routeName === 'product-service.show' => 'product_service.item.view',
            $routeName === 'product-service.create', $routeName === 'product-service.store' => 'product_service.item.create',
            $routeName === 'product-service.edit', $routeName === 'product-service.update' => 'product_service.item.update',
            $routeName === 'product-service.destroy' => 'product_service.item.delete',

            $routeName === 'product-service.stock.index', $routeName === 'product-service.stock' => 'inventory.stock.view',
            $routeName === 'product-service.adjust-stock', $routeName === 'product-service.stock.store' => 'inventory.stock.adjust',

            $routeName === 'product-service.categories.index' => 'product_service.category.view',
            $routeName === 'product-service.categories.store' => 'product_service.category.create',
            $routeName === 'product-service.categories.update' => 'product_service.category.update',
            $routeName === 'product-service.categories.destroy' => 'product_service.category.delete',

            $routeName === 'product-service.units.index' => 'product_service.unit.view',
            $routeName === 'product-service.units.store' => 'product_service.unit.create',
            $routeName === 'product-service.units.update' => 'product_service.unit.update',
            $routeName === 'product-service.units.destroy' => 'product_service.unit.delete',

            $routeName === 'product-service.taxes.index' => 'product_service.tax.view',
            $routeName === 'product-service.taxes.store' => 'product_service.tax.create',
            $routeName === 'product-service.taxes.update' => 'product_service.tax.update',
            $routeName === 'product-service.taxes.destroy' => 'product_service.tax.delete',

            $routeName === 'warehouses.index', $routeName === 'warehouses.show' => 'inventory.warehouse.view',
            $routeName === 'warehouses.create', $routeName === 'warehouses.store' => 'inventory.warehouse.create',
            $routeName === 'warehouses.edit', $routeName === 'warehouses.update' => 'inventory.warehouse.update',
            $routeName === 'warehouses.destroy' => 'inventory.warehouse.delete',

            $routeName === 'transfers.index', $routeName === 'transfers.show' => 'inventory.transfer.view',
            $routeName === 'transfers.create', $routeName === 'transfers.store' => 'inventory.transfer.create',
            $routeName === 'transfers.destroy' => 'inventory.transfer.delete',

            $routeName === 'inventory.movements' => 'inventory.movement.view',
            str_starts_with($routeName, 'inventory.reports') => 'inventory.report.view',

            $routeName === 'api.product-service.items.index' => 'product_service.item.view',
            default => null,
        };
    }
}
