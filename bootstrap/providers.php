<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleLoaderServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    ModuleLoaderServiceProvider::class,
    SettingsServiceProvider::class,
];
