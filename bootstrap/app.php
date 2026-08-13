<?php

use App\Console\Commands\InstallBusinessOs;
use App\Http\Middleware\CheckModuleStatus;
use App\Http\Middleware\EnsureApiWorkspace;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Installed;
use App\Http\Middleware\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

require_once __DIR__.'/../app/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([InstallBusinessOs::class])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            RequestId::class,
            Installed::class,
            HandleInertiaRequests::class,
            EnsureTenantContext::class,
        ]);

        $middleware->api(append: [RequestId::class]);

        $middleware->alias([
            'module.status' => CheckModuleStatus::class,
            'api.workspace' => EnsureApiWorkspace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
