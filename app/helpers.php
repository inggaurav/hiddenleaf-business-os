<?php

use App\Domain\SaaS\Services\SaaSSubscriptionService;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

if (! function_exists('creatorId')) {
    function creatorId(): int
    {
        if (Auth::user() && Auth::user()->role === 'super_admin') {
            return 1;
        }

        return Auth::id() ?: 1;
    }
}

if (! function_exists('admin_setting')) {
    function admin_setting(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('key', $key)->whereNull('workspace_id')->first();

        return $setting ? $setting->value : $default;
    }
}

if (! function_exists('getAdminAllSetting')) {
    function getAdminAllSetting(): array
    {
        return Setting::whereNull('workspace_id')->pluck('value', 'key')->toArray();
    }
}

if (! function_exists('setSetting')) {
    function setSetting(string $key, mixed $value, ?int $workspaceId = null, bool $forTenant = false): void
    {
        Setting::updateOrCreate(
            ['key' => $key, 'workspace_id' => $workspaceId],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );
    }
}

if (! function_exists('assignPlan')) {
    function assignPlan(int $planId, string $duration = 'Month', array|string $modules = [], array $counters = [], ?int $userId = null): array
    {
        return app(SaaSSubscriptionService::class)->assignPlan($planId, $duration, $modules, $counters, $userId);
    }
}

if (! function_exists('applyCouponDiscount')) {
    function applyCouponDiscount(string $couponCode, float $totalAmount, ?int $userId = null): array
    {
        return app(SaaSSubscriptionService::class)->applyCouponDiscount($couponCode, $totalAmount, $userId);
    }
}

if (! function_exists('recordCouponUsage')) {
    function recordCouponUsage(int $couponId, int $userId, ?string $orderId = null): void
    {
        app(SaaSSubscriptionService::class)->recordCouponUsage($couponId, $userId, $orderId);
    }
}
