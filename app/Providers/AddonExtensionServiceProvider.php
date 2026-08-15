<?php

namespace App\Providers;

use App\Services\AddonExtensionRegistrar;
use Illuminate\Support\ServiceProvider;

class AddonExtensionServiceProvider extends ServiceProvider
{
    public function boot(AddonExtensionRegistrar $registrar): void
    {
        $registrar->register();
    }
}
