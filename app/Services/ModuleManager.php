<?php

namespace App\Services;

use App\Domain\Modules\SecureModuleInstaller;
use App\Models\Addon;
use App\Models\Plan;
use App\Modules\AccountModule;
use App\Modules\HRMModule;
use App\Modules\LandingPageModule;
use App\Modules\LeadModule;
use App\Modules\POSModule;
use App\Modules\ProductServiceModule;
use App\Modules\TasklyModule;
use HiddenLeaf\Kernel\Contracts\ModuleContract;
use HiddenLeaf\Kernel\Registries\ModuleRegistry;
use Illuminate\Support\Facades\DB;

class ModuleManager
{
    public function __construct(
        protected ModuleRegistry $registry,
        protected AddonManager $addons,
    ) {
        $this->registerBundledModules();
    }

    protected function registerBundledModules(): void
    {
        foreach ([new AccountModule, new HRMModule, new LeadModule, new TasklyModule, new POSModule, new ProductServiceModule, new LandingPageModule] as $mod) {
            $this->registry->register($mod);
        }
    }

    public function getAllModules(): array { return $this->registry->all(); }
    public function getModule(string $alias): ?ModuleContract { return $this->registry->get(strtolower($alias)); }

    public function isModuleEnabledForWorkspace(string $moduleAlias, ?int $workspaceId = null): bool
    {
        $module = $this->getModule($moduleAlias);
        if (! $module) { return false; }
        if (! $workspaceId) { return $module->isEnabled(); }

        $workspace = \App\Models\Workspace::query()->with('organization')->find($workspaceId);
        return $workspace ? $this->addons->isActiveForWorkspace($workspace, $moduleAlias) : false;
    }

    public function isModuleIncludedInPlan(string $moduleAlias, int $planId): bool
    {
        $plan = Plan::find($planId);
        if (! $plan || empty($plan->modules)) { return false; }
        $modules = array_map(static fn (mixed $module): string => strtolower((string) $module), $plan->modules ?? []);
        return in_array(strtolower($moduleAlias), $modules, true);
    }

    public function installFromZip(string $zipPath): array
    {
        try {
            $manifest = app(SecureModuleInstaller::class)->install($zipPath);

            $addon = DB::transaction(function () use ($manifest): Addon {
                return Addon::query()->updateOrCreate(
                    ['addon_id' => (string) $manifest['id']],
                    [
                        'alias' => strtolower((string) $manifest['alias']),
                        'name' => (string) $manifest['name'],
                        'version' => (string) $manifest['version'],
                        'minimum_core' => (string) $manifest['minimum_core'],
                        'dependencies' => $manifest['dependencies'],
                        'manifest' => $manifest,
                        'status' => 'installed',
                    ]
                );
            });

            $this->registry->register(new ManifestAddonModule($addon));

            return ['success' => true, 'message' => "Module {$manifest['name']} installed successfully.", 'module' => $manifest];
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }
}
