<?php

namespace App\Http\Controllers\Diagnostics;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HealthCheckController extends Controller
{
    /**
     * Liveness probe: checks if the application container/process is running.
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'live',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0-rc1'),
        ]);
    }

    /**
     * Readiness probe: verifies critical dependencies (Database, Storage, Cache).
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => false,
            'storage' => false,
            'cache' => false,
        ];
        $errors = [];

        // 1. Database Check
        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable $e) {
            $errors['database'] = 'Database connection failed: '.$e->getMessage();
        }

        // 2. Storage Check
        try {
            $testFile = storage_path('framework/cache/health_check_'.uniqid().'.tmp');
            File::put($testFile, 'ok');
            if (File::exists($testFile)) {
                File::delete($testFile);
                $checks['storage'] = true;
            }
        } catch (\Throwable $e) {
            $errors['storage'] = 'Storage is not writable: '.$e->getMessage();
        }

        // 3. Cache Check
        try {
            $cacheKey = 'health_check_key_'.uniqid();
            Cache::put($cacheKey, 'ok', 5);
            if (Cache::get($cacheKey) === 'ok') {
                Cache::forget($cacheKey);
                $checks['cache'] = true;
            }
        } catch (\Throwable $e) {
            $errors['cache'] = 'Cache system failed: '.$e->getMessage();
        }

        $allReady = $checks['database'] && $checks['storage'] && $checks['cache'];
        $status = $allReady ? 200 : 503;

        return response()->json([
            'status' => $allReady ? 'ready' : 'unready',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'errors' => $errors,
        ], $status);
    }
}
