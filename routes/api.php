<?php

use Illuminate\Support\Facades\Route;
use HiddenLeaf\Http\Controllers\Api\V1\LicensingController;

Route::prefix('v1')->group(function () {
    Route::post('/licensing/activate', [LicensingController::class, 'activate']);
    Route::post('/licensing/deactivate', [LicensingController::class, 'deactivate']);
    Route::post('/licensing/validate', [LicensingController::class, 'validateLicense']);
    Route::get('/licensing/entitlements', [LicensingController::class, 'entitlements']);
});
