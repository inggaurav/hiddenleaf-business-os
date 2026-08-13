<?php

namespace App\Providers;

use App\Contracts\AssistantProviderContract;
use App\Contracts\MessengerTransportContract;
use App\Services\Assistant\LocalAssistantProvider;
use App\Services\CacheMessengerTransport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MessengerTransportContract::class, CacheMessengerTransport::class);
        $this->app->bind(AssistantProviderContract::class, LocalAssistantProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
