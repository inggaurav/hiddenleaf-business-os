<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
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
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $settings = \App\Models\Setting::all();
                foreach ($settings as $setting) {
                    config()->set('settings.' . $setting->key, $setting->value);
                }
            }
        } catch (\Exception $e) {
            // Do nothing during setup/migrations
        }
    }
}
