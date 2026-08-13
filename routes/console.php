<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:install', function () {
    $this->call(\HiddenLeaf\Console\Commands\InstallCommand::class);
})->purpose('Bootstrap and install HiddenLeaf BusinessOS core platform');
