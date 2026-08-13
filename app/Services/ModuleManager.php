<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\UserActiveModule;
use App\Modules\AccountModule;
use App\Modules\HRMModule;
use App\Modules\LandingPageModule;
use App\Modules\LeadModule;
use App\Modules\POSModule;
use App\Modules\ProductServiceModule;
use App\Modules\TasklyModule;
use HiddenLeaf\Kernel\Contracts\ModuleContract;
use HiddenLeaf\Kernel\Registries\ModuleRegistry;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ModuleManager
{
    protected ModuleRegistry $registry;

    public function __construct(ModuleRegistry $registry)
    {
        $this->registry = $registry;
        $this->registerBundledModules();
    }

    protected function registerBundledModules(): void
    {
        $bundled = [
            new AccountModule,
            new HRMModule,
            new LeadModule,
            new TasklyModule,
            new POSModule,
            new ProductServiceModule,
            new LandingPageModule,
        ];

        foreach ($bundled as $mod) {
            $this->registry->register($mod);
        }
    }

    public function getAllModules(): array
    {
        return $this->registry->all();
    }

    public function getModule(string $alias): ?ModuleContract
    {
        return $this->registry->get(strtolower($alias));
    }

    public function isModuleEnabledForWorkspace(string $moduleAlias, ?int $workspaceId = null): bool
    {
        $module = $this->getModule($moduleAlias);
        if (! $module) {
            return false;
        }

        if (! $workspaceId) {
            return $module->isEnabled();
        }

        return UserActiveModule::where('workspace_id', $workspaceId)
            ->where(function ($q) use ($moduleAlias) {
                $q->where('module_name', $moduleAlias)
                    ->orWhere('module', $moduleAlias);
            })
            ->exists();
    }

    public function isModuleIncludedInPlan(string $moduleAlias, int $planId): bool
    {
        $plan = Plan::find($planId);
        if (! $plan || empty($plan->modules)) {
            return false;
        }

        $modules = is_array($plan->modules) ? $plan->modules : json_decode($plan->modules, true);

        return in_array(strtolower($moduleAlias), array_map('strtolower', $modules ?? []));
    }

    public function installFromZip(string $zipPath): array
    {
        if (! class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'ZipArchive PHP extension is required.'];
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return ['success' => false, 'message' => 'Unable to open zip archive.'];
        }

        $extractPath = base_path('modules_temp_'.uniqid());
        $zip->extractTo($extractPath);
        $zip->close();

        // Check for module.json
        $manifestPath = $extractPath.'/module.json';
        if (! File::exists($manifestPath)) {
            File::deleteDirectory($extractPath);

            return ['success' => false, 'message' => 'Invalid module package: module.json manifest not found.'];
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $moduleName = $manifest['name'] ?? null;
        $alias = strtolower($manifest['alias'] ?? $moduleName);

        if (! $moduleName) {
            File::deleteDirectory($extractPath);

            return ['success' => false, 'message' => 'Invalid manifest: module name missing.'];
        }

        $destDir = base_path("modules/{$moduleName}");
        if (! File::exists(base_path('modules'))) {
            File::makeDirectory(base_path('modules'), 0755, true);
        }

        File::moveDirectory($extractPath, $destDir, true);

        return [
            'success' => true,
            'message' => "Module {$moduleName} installed successfully.",
            'module' => $manifest,
        ];
    }
}
