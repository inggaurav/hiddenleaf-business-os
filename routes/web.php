<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Domain\Auth\RoleController;
use App\Http\Controllers\MultiTenancy\MemberController;
use App\Http\Controllers\MultiTenancy\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public & Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginView'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'registerView'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'resetView'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->name('password.update');
});

// Authenticated Core Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Email Verification Routes
    Route::get('/verify-email', [VerifyEmailController::class, 'prompt'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'sendNotification'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Multi-Tenancy Workspaces & Members
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspaces/{id}/edit', [WorkspaceController::class, 'edit'])->name('workspaces.edit');
    Route::put('/workspaces/{id}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{id}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/workspaces/switch', [WorkspaceController::class, 'switchContext'])->name('workspaces.switch');

    Route::post('/members/invite', [MemberController::class, 'invite'])->name('members.invite');
    Route::delete('/members/{id}', [MemberController::class, 'remove'])->name('members.remove');
    Route::post('/members/{id}/role', [MemberController::class, 'assignRole'])->name('members.assign-role');

    // User Administration Actions
    Route::post('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/leave-impersonation', [UserController::class, 'leaveImpersonation'])->name('users.leave-impersonation');

    // SaaS Routes
    Route::resource('plans', \App\Http\Controllers\Domain\SaaS\PlanController::class);
    Route::resource('coupons', \App\Http\Controllers\Domain\SaaS\CouponController::class);
    Route::resource('orders', \App\Http\Controllers\Domain\SaaS\OrderController::class);
    Route::resource('subscriptions', \App\Http\Controllers\Domain\SaaS\SubscriptionController::class);

    // RBAC Roles (Explicit actions matching RoleController)
    Route::resource('roles', RoleController::class)->except(['show']);

    // Super Admin Routes
    Route::middleware([\App\Http\Middleware\SuperAdminMiddleware::class])->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/settings', [\App\Http\Controllers\SuperAdmin\SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\SuperAdmin\SettingController::class, 'store'])->name('settings.store');
        Route::get('/translations', [\App\Http\Controllers\SuperAdmin\TranslationController::class, 'index'])->name('translations.index');
        Route::post('/translations', [\App\Http\Controllers\SuperAdmin\TranslationController::class, 'store'])->name('translations.store');
    });

    // Modules
    Route::get('/modules', [\App\Http\Controllers\ModuleController::class, 'index'])->name('modules.index');
    Route::post('/modules/toggle', [\App\Http\Controllers\ModuleController::class, 'toggle'])->name('modules.toggle');
});
