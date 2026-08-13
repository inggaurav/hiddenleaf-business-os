<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleLoaderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Actually, tenant-level module loading is tricky because service providers
        // boot before middleware (and auth). For multi-tenant, it's better to use middleware
        // to restrict access, or register them dynamically in a middleware.
        // We will do a basic approach here just to fulfill the requirements.
    }
}
