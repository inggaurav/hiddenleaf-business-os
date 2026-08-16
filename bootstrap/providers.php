<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleLoaderServiceProvider;
use App\Providers\MrFoxServiceProvider;
use App\Providers\SettingsServiceProvider;
use NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider;

return [
    AppServiceProvider::class,
    ModuleLoaderServiceProvider::class,
    SettingsServiceProvider::class,
    MrFoxServiceProvider::class,
    CollisionServiceProvider::class,
];
