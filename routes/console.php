<?php

use HiddenLeaf\Console\Commands\InstallCommand;
use Illuminate\Support\Facades\Artisan;

Artisan::command('app:install', function () {
    $this->call(InstallCommand::class);
})->purpose('Bootstrap and install HiddenLeaf BusinessOS core platform');
