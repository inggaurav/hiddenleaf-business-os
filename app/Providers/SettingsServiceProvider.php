<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
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
            if (Schema::hasTable('settings')) {
                $settings = Schema::hasColumn('settings', 'scope')
                    ? Setting::where('scope', 'platform')->where('scope_id', 0)->where('is_encrypted', false)->get()
                    : Setting::whereNull('workspace_id')->get();
                foreach ($settings as $setting) {
                    config()->set('settings.'.$setting->key, $setting->value);
                }
            }
        } catch (\Throwable $e) {
            // Do nothing during setup/migrations/offline CLI
        }
    }
}
