<?php

namespace App\Http\Controllers;

use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Services\ModuleManager;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ModuleController extends Controller
{
    protected ModuleManager $moduleManager;

    public function __construct(ModuleManager $moduleManager, private readonly AuditLogger $auditLogger)
    {
        $this->moduleManager = $moduleManager;
    }

    public function index(Request $request)
    {
        $workspaceId = $request->session()->get('active_workspace_id');
        $user = Auth::user();

        $modules = $this->moduleManager->getAllModules();
        $activeModules = $workspaceId
            ? UserActiveModule::where('workspace_id', $workspaceId)->pluck('module_name')->toArray()
            : [];

        $formattedModules = [];
        foreach ($modules as $module) {
            $formattedModules[] = [
                'name' => $module->getName(),
                'alias' => $module->getAlias(),
                'version' => $module->getVersion(),
                'description' => $module->getDescription(),
                'active' => in_array($module->getAlias(), $activeModules) || in_array($module->getName(), $activeModules),
                'permissions' => $module->getPermissions(),
                'navigation' => $module->getNavigation(),
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

        if (! $workspaceId) {
            abort(403, 'No active workspace');
        }

        $workspace = Workspace::query()->with('organization')->find($workspaceId);

        if (! $workspace || ! $user->canInWorkspace('modules.manage', $workspace)) {
            abort(403, 'You are not authorized to manage modules for this workspace.');
        }

        $moduleName = $validated['module_name'];
        $alias = strtolower($moduleName);

        DB::transaction(function () use ($request, $workspaceId, $workspace, $user, $moduleName, $alias) {
            if ($request->boolean('active')) {
                UserActiveModule::updateOrCreate([
                    'workspace_id' => $workspaceId,
                    'module_name' => $moduleName,
                ], [
                    'module' => $alias,
                    'user_id' => $user->id,
                ]);
            } else {
                UserActiveModule::where('workspace_id', $workspaceId)
                    ->where(function ($q) use ($alias, $moduleName) {
                        $q->where('module_name', $alias)
                            ->orWhere('module', $alias)
                            ->orWhere('module_name', $moduleName);
                    })
                    ->delete();
            }

            $this->auditLogger->log(
                $user->id,
                $workspace->organization_id,
                $workspace->id,
                $request->boolean('active') ? 'module.activated' : 'module.deactivated',
                'module',
                $moduleName,
                critical: true,
            );
        });

        return redirect()->back()->with('success', 'Module status updated.');
    }

    public function install(Request $request)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403, 'Only super administrators can install modules.');
        }

        $request->validate([
            'file' => 'required|file|mimes:zip|max:51200',
        ]);

        $file = $request->file('file');
        $tempPath = $file->getRealPath();

        $result = $this->moduleManager->installFromZip($tempPath);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }
}
