<?php

namespace App\Providers;

use App\Contracts\MessengerTransportContract;
use App\Services\CacheMessengerTransport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MessengerTransportContract::class, CacheMessengerTransport::class);
    }

    public function boot(): void
    {
        //
    }
}
