<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class DashboardDestinationService
{
    /**
     * WorkDo-style module dashboard ordering. HiddenLeaf route names stay native.
     */
    private const CANDIDATES = [
        ['module' => 'taskly', 'route' => 'taskly.dashboard', 'permission' => 'taskly.view'],
        ['module' => 'account', 'route' => 'accounting.dashboard', 'permission' => 'account.view'],
        ['module' => 'hrm', 'route' => 'hrm.dashboard', 'permission' => 'hrm.view'],
        ['module' => 'lead', 'route' => 'crm.dashboard', 'permission' => 'crm.view'],
        ['module' => 'pos', 'route' => 'pos.index', 'permission' => 'pos.dashboard.view'],
    ];

    public function firstPermittedRoute(User $user, Workspace $workspace, array $enabledModules): ?string
    {
        $enabled = array_map(
            static fn ($module) => strtolower((string) $module),
            $enabledModules
        );

        foreach (self::CANDIDATES as $candidate) {
            if (! in_array($candidate['module'], $enabled, true)) {
                continue;
            }

            if ($user->canInWorkspace($candidate['permission'], $workspace)) {
                return $candidate['route'];
            }
        }

        return null;
    }
}
