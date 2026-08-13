<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HelpdeskApiController;
use App\Http\Controllers\Api\V1\MediaApiController;
use App\Http\Controllers\Api\V1\PlanApiController;
use App\Http\Controllers\Api\V1\SalesProcurementApiController;
use App\Http\Controllers\Api\V1\WorkspaceApiController;
use HiddenLeaf\Http\Controllers\Api\V1\LicensingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Auth & Plans
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/plans', [PlanApiController::class, 'index']);
    Route::get('/plans/{plan}', [PlanApiController::class, 'show']);

    // Licensing Endpoints
    Route::post('/licensing/activate', [LicensingController::class, 'activate']);
    Route::post('/licensing/deactivate', [LicensingController::class, 'deactivate']);
    Route::post('/licensing/validate', [LicensingController::class, 'validateLicense']);
    Route::get('/licensing/entitlements', [LicensingController::class, 'entitlements']);

    // Authenticated Sanctum Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Workspaces
        Route::get('/workspaces', [WorkspaceApiController::class, 'index']);
        Route::get('/workspaces/{workspace}', [WorkspaceApiController::class, 'show']);

        // Sales & Procurement
        Route::get('/warehouses', [SalesProcurementApiController::class, 'warehouses']);
        Route::get('/purchase-invoices', [SalesProcurementApiController::class, 'purchaseInvoices']);
        Route::get('/sales-invoices', [SalesProcurementApiController::class, 'salesInvoices']);
        Route::get('/sales-proposals', [SalesProcurementApiController::class, 'salesProposals']);

        // Helpdesk
        Route::get('/helpdesk/tickets', [HelpdeskApiController::class, 'tickets']);
        Route::post('/helpdesk/tickets', [HelpdeskApiController::class, 'storeTicket']);
        Route::get('/helpdesk/tickets/{ticket}', [HelpdeskApiController::class, 'ticketDetails']);

        // Media
        Route::get('/media', [MediaApiController::class, 'index']);
        Route::post('/media/upload', [MediaApiController::class, 'upload']);
    });
});
