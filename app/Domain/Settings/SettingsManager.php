<?php

namespace App\Domain\Settings;

use App\Models\Setting;
use App\Models\Workspace;

class SettingsManager
{
    public function get(string $key, mixed $default = null, ?Workspace $workspace = null): mixed
    {
        try {
            if ($workspace) {
                $setting = Setting::where('key', $key)
                    ->where('workspace_id', $workspace->id)
                    ->first();

                if ($setting) {
                    return $setting->value;
                }
            }

            $globalSetting = Setting::where('key', $key)
                ->whereNull('workspace_id')
                ->first();

            if ($globalSetting) {
                return $globalSetting->value;
            }
        } catch (\Throwable) {
            // fallback
        }

        return config("settings.{$key}", $default);
    }
}
