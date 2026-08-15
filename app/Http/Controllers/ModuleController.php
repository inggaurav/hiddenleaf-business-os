<?php

namespace App\Http\Controllers;

use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Services\AddonManager;
use App\Services\ManifestAddonModule;
use App\Services\ModuleManager;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class ModuleController extends Controller
{
    public function __construct(
        protected ModuleManager $moduleManager,
        private readonly AuditLogger $auditLogger,
        private readonly AddonManager $addonManager,
    ) {}

    public function index(Request $request)
    {
        $workspaceId = $request->session()->get('active_workspace_id');
        $user = Auth::user();
        $workspace = $workspaceId ? Workspace::with('organization')->find($workspaceId) : null;
        $modules = $this->moduleManager->getAllModules();
        $activeModules = $workspaceId
            ? UserActiveModule::where('workspace_id', $workspaceId)->pluck('module_name')->map(fn ($value) => strtolower((string) $value))->toArray()
            : [];

        $formattedModules = [];
        foreach ($modules as $module) {
            $alias = strtolower($module->getAlias());
            $formattedModules[] = [
                'name' => $module->getName(),
                'alias' => $alias,
                'version' => $module->getVersion(),
                'description' => $module->getDescription(),
                'active' => $workspace ? $this->addonManager->isActiveForWorkspace($workspace, $alias) : in_array($alias, $activeModules, true),
                'entitled' => $workspace ? ($user->isSuperAdmin() || $this->addonManager->isPlanEntitled($workspace, $alias)) : $user->isSuperAdmin(),
                'permissions' => $module->getPermissions(),
                'navigation' => $module->getNavigation(),
                'dependencies' => method_exists($module, 'dependencies') ? $module->dependencies() : [],
            ];
        }

        foreach ($this->addonManager->installed() as $addon) {
            if (collect($formattedModules)->contains(fn ($row) => $row['alias'] === strtolower($addon->alias))) {
                continue;
            }
            $module = new ManifestAddonModule($addon);
            $formattedModules[] = [
                'name' => $module->getName(),
                'alias' => $module->getAlias(),
                'version' => $module->getVersion(),
                'description' => $module->getDescription(),
                'active' => $workspace ? $this->addonManager->isActiveForWorkspace($workspace, $module->getAlias()) : false,
                'entitled' => $workspace ? ($user->isSuperAdmin() || $this->addonManager->isPlanEntitled($workspace, $module->getAlias())) : $user->isSuperAdmin(),
                'permissions' => $module->getPermissions(),
                'navigation' => $module->getNavigation(),
                'dependencies' => $module->dependencies(),
            ];
        }

        return Inertia::render('Modules/Index', [
            'modules' => $formattedModules,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'module_name' => 'required|string',
            'active' => 'required|boolean',
        ]);

        $workspaceId = $request->session()->get('active_workspace_id');
        $user = Auth::user();
        abort_unless($workspaceId, 403, 'No active workspace');

        $workspace = Workspace::with('organization')->find($workspaceId);
        abort_unless($workspace && $user->canInWorkspace('modules.manage', $workspace), 403, 'You are not authorized to manage modules for this workspace.');

        $alias = strtolower(trim($validated['module_name']));
        $isAddon = $this->addonManager->find($alias) !== null;

        try {
            DB::transaction(function () use ($request, $workspace, $user, $alias, $isAddon) {
                if ($request->boolean('active')) {
                    if ($isAddon) {
                        $this->addonManager->activate($workspace, $alias, $user->id, $user->isSuperAdmin());
                    } else {
                        if (! $user->isSuperAdmin() && ! $this->addonManager->isPlanEntitled($workspace, $alias)) {
                            throw new RuntimeException('This module is not included in the current plan.');
                        }
                        UserActiveModule::updateOrCreate([
                            'workspace_id' => $workspace->id,
                            'module_name' => $alias,
                        ], [
                            'user_id' => $user->id,
                        ]);
                    }
                } else {
                    if ($isAddon) {
                        $this->addonManager->deactivate($workspace, $alias);
                    } else {
                        UserActiveModule::where('workspace_id', $workspace->id)
                            ->whereRaw('LOWER(module_name) = ?', [$alias])
                            ->delete();
                    }
                }

                $this->auditLogger->log(
                    $user->id,
                    $workspace->organization_id,
                    $workspace->id,
                    $request->boolean('active') ? 'module.activated' : 'module.deactivated',
                    'module',
                    $alias,
                    critical: true,
                );
            });
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Module status updated.');
    }

    public function install(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin(), 403, 'Only super administrators can install modules.');

        $request->validate(['file' => 'required|file|mimes:zip|max:51200']);
        $result = $this->moduleManager->installFromZip($request->file('file')->getRealPath());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }
}
