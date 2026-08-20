<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HelpdeskApiController;
use App\Http\Controllers\Api\V1\MediaApiController;
use App\Http\Controllers\Api\V1\PlanApiController;
use App\Http\Controllers\Api\V1\ProductServiceApiController;
use App\Http\Controllers\Api\V1\SalesProcurementApiController;
use App\Http\Controllers\Api\V1\UserDirectoryApiController;
use App\Http\Controllers\Api\V1\WorkspaceApiController;
use HiddenLeaf\Http\Controllers\Api\V1\LicensingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Auth & Plans
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/plans', [PlanApiController::class, 'index']);
    Route::get('/plans/{plan}', [PlanApiController::class, 'show']);

    // License-authority protocol endpoints never register on customer installations.
    if (config('licensing.server_enabled')) {
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/licensing/activate', [LicensingController::class, 'activate']);
            Route::post('/licensing/deactivate', [LicensingController::class, 'deactivate']);
            Route::post('/licensing/validate', [LicensingController::class, 'validateLicense']);
            Route::get('/licensing/entitlements', [LicensingController::class, 'entitlements']);
        });
    }

    // Authenticated Sanctum Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::patch('/profile', [AccountController::class, 'updateProfile']);
        Route::put('/password', [AccountController::class, 'updatePassword']);
        Route::delete('/account', [AccountController::class, 'destroy']);
        Route::get('/tokens', [AccountController::class, 'tokens']);
        Route::post('/tokens', [AccountController::class, 'storeToken']);
        Route::delete('/tokens/{token}', [AccountController::class, 'destroyToken'])->whereNumber('token');

        // Workspaces
        Route::get('/workspaces', [WorkspaceApiController::class, 'index']);
        Route::get('/workspaces/{workspace}', [WorkspaceApiController::class, 'show']);

        Route::middleware('api.workspace')->group(function () {
            Route::get('/users', [UserDirectoryApiController::class, 'users']);
            Route::get('/staff-users', [UserDirectoryApiController::class, 'users'])->defaults('type', 'staff');
            Route::get('/client-users', [UserDirectoryApiController::class, 'users'])->defaults('type', 'client');
            Route::get('/vendor-users', [UserDirectoryApiController::class, 'users'])->defaults('type', 'vendor');
            Route::get('/subscription', [UserDirectoryApiController::class, 'subscription']);
            Route::get('/products-services', [ProductServiceApiController::class, 'index']);
            Route::get('/products-services/{item}', [ProductServiceApiController::class, 'show'])->whereNumber('item');

            Route::get('/warehouses', [SalesProcurementApiController::class, 'warehouses']);
            Route::get('/purchase-invoices', [SalesProcurementApiController::class, 'purchaseInvoices']);
            Route::get('/sales-invoices', [SalesProcurementApiController::class, 'salesInvoices']);
            Route::get('/sales-proposals', [SalesProcurementApiController::class, 'salesProposals']);

            Route::get('/helpdesk/tickets', [HelpdeskApiController::class, 'tickets']);
            Route::post('/helpdesk/tickets', [HelpdeskApiController::class, 'storeTicket']);
            Route::get('/helpdesk/tickets/{ticket}', [HelpdeskApiController::class, 'ticketDetails']);

            Route::get('/media', [MediaApiController::class, 'index']);
            Route::post('/media/upload', [MediaApiController::class, 'upload']);

            // Mr. Fox Executive Intelligence API
            Route::post('/mr-fox/chat', [\App\Http\Controllers\MrFox\MrFoxChatController::class, 'chat']);
            Route::get('/mr-fox/conversations', [\App\Http\Controllers\MrFox\MrFoxChatController::class, 'getConversations']);
            Route::get('/mr-fox/conversations/{id}', [\App\Http\Controllers\MrFox\MrFoxChatController::class, 'getConversationMessages']);
            Route::get('/mr-fox/insights', [\App\Http\Controllers\MrFox\MrFoxChatController::class, 'getInsights']);
            Route::post('/mr-fox/actions/{id}/approve', [\App\Http\Controllers\MrFox\MrFoxChatController::class, 'approveAction']);
            Route::post('/mr-fox/actions/{id}/reject', [\App\Http\Controllers\MrFox\MrFoxChatController::class, 'rejectAction']);
        });
    });
});
