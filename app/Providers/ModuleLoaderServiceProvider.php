<?php

namespace App\Providers;

use App\Models\Addon;
use App\Services\AddonExtensionRegistrar;
use App\Services\AddonManager;
use App\Services\ManifestAddonModule;
use HiddenLeaf\Kernel\Registries\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ModuleLoaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn (): ModuleRegistry => new ModuleRegistry());
        $this->app->singleton(AddonManager::class);
    }

    public function boot(ModuleRegistry $registry): void
    {
        // Providers boot before tenant middleware/auth. Installed metadata is
        // registered globally; workspace and SaaS-plan access is enforced later.
        if (Schema::hasTable('addons')) {
            Addon::query()
                ->whereIn(DB::raw('LOWER(status)'), ['installed', 'enabled', 'active'])
                ->orderBy('id')
                ->each(function (Addon $addon) use ($registry): void {
                    $registry->register(new ManifestAddonModule($addon));
                });
        }

        // MrFox/Automation registries are registered by later providers, so wait
        // until the entire application has booted before loading optional add-on extensions.
        $this->app->booted(function (): void {
            app(AddonExtensionRegistrar::class)->register();
        });
    }
}
