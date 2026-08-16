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
    private const FIRST_PARTY_CAPABILITIES = [
        'sales' => ['name' => 'Sales', 'description' => 'Customer invoices, proposals, returns and revenue workflows.'],
        'procurement' => ['name' => 'Procurement', 'description' => 'Vendor bills, purchasing, returns and payable workflows.'],
        'communications' => ['name' => 'Unified Communications', 'description' => 'Unified inbox and connected communication providers.'],
        'automations' => ['name' => 'Automations', 'description' => 'Deterministic event-condition-action business automations.'],
        'missions' => ['name' => 'Mr Fox Missions', 'description' => 'Governed multi-step AI mission planning and execution.'],
        'command_center' => ['name' => 'Command Center', 'description' => 'Executive business health, priorities, signals and approvals.'],
        'knowledge' => ['name' => 'Knowledge', 'description' => 'Grounded business knowledge and retrieval capabilities.'],
        'helpdesk' => ['name' => 'Helpdesk', 'description' => 'Support tickets, categories, replies and service workflows.'],
        'media' => ['name' => 'Media Library', 'description' => 'Workspace files and business media storage.'],
    ];

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
        abort_unless($user->isSuperAdmin() || ($workspace && $user->canInWorkspace('modules.manage', $workspace)), 403, 'You are not authorized to view module administration.');
        $activeModules = $workspaceId
            ? UserActiveModule::where('workspace_id', $workspaceId)->pluck('module_name')->map(fn ($value) => strtolower((string) $value))->toArray()
            : [];

        $formattedModules = [];
        foreach ($this->moduleManager->getAllModules() as $module) {
            $alias = strtolower($module->getAlias());
            $formattedModules[] = $this->moduleRow(
                $workspace,
                $user,
                $alias,
                $module->getName(),
                $module->getVersion(),
                $module->getDescription(),
                $module->getPermissions(),
                $module->getNavigation(),
                method_exists($module, 'dependencies') ? $module->dependencies() : [],
                in_array($alias, $activeModules, true),
            );
        }

        foreach (self::FIRST_PARTY_CAPABILITIES as $alias => $meta) {
            if (collect($formattedModules)->contains(fn ($row) => $row['alias'] === $alias)) {
                continue;
            }
            $formattedModules[] = $this->moduleRow(
                $workspace,
                $user,
                $alias,
                $meta['name'],
                (string) config('app.version', '1.0.0'),
                $meta['description'],
                [],
                [],
                [],
                in_array($alias, $activeModules, true),
            );
        }

        foreach ($this->addonManager->installed() as $addon) {
            if (collect($formattedModules)->contains(fn ($row) => $row['alias'] === strtolower($addon->alias))) {
                continue;
            }
            $module = new ManifestAddonModule($addon);
            $formattedModules[] = $this->moduleRow(
                $workspace,
                $user,
                $module->getAlias(),
                $module->getName(),
                $module->getVersion(),
                $module->getDescription(),
                $module->getPermissions(),
                $module->getNavigation(),
                $module->dependencies(),
                false,
            );
        }

        usort($formattedModules, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return Inertia::render('Modules/Index', [
            'modules' => $formattedModules,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'canManage' => $workspace ? $user->canInWorkspace('modules.manage', $workspace) : $user->isSuperAdmin(),
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
                        UserActiveModule::firstOrCreate([
                            'workspace_id' => $workspace->id,
                            'module_name' => $alias,
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

    private function moduleRow(?Workspace $workspace, $user, string $alias, string $name, string $version, string $description, array $permissions, array $navigation, array $dependencies, bool $fallbackActive): array
    {
        $alias = strtolower($alias);

        return [
            'name' => $name,
            'alias' => $alias,
            'version' => $version,
            'description' => $description,
            'active' => $workspace ? $this->addonManager->isActiveForWorkspace($workspace, $alias) : $fallbackActive,
            'entitled' => $workspace ? ($user->isSuperAdmin() || $this->addonManager->isPlanEntitled($workspace, $alias)) : $user->isSuperAdmin(),
            'permissions' => $permissions,
            'navigation' => $navigation,
            'dependencies' => $dependencies,
        ];
    }
}
