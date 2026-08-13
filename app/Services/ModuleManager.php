<?php

namespace App\Services;

use App\Domain\Modules\SecureModuleInstaller;
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
        try {
            $manifest = app(SecureModuleInstaller::class)->install($zipPath);

            return ['success' => true, 'message' => "Module {$manifest['name']} installed successfully.", 'module' => $manifest];
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }
}
