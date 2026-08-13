<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Domain\Auth\RoleController;
use App\Http\Controllers\Domain\SaaS\CouponController;
use App\Http\Controllers\Domain\SaaS\OrderController;
use App\Http\Controllers\Domain\SaaS\PlanController;
use App\Http\Controllers\Domain\SaaS\SubscriptionController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MultiTenancy\MemberController;
use App\Http\Controllers\MultiTenancy\WorkspaceController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\EmailTemplateController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\SettingController;
use App\Http\Controllers\SuperAdmin\TranslationController;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Models\AuditLog;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'setup'])->name('install.setup');

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
        $usersCount = User::count();
        $workspacesCount = Workspace::count();
        $recentLogs = AuditLog::with('user')->latest()->take(5)->get();
        $ticketsCount = HelpdeskTicket::count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'users' => $usersCount,
                'workspaces' => $workspacesCount,
                'tickets' => $ticketsCount,
            ],
            'recentLogs' => $recentLogs,
        ]);
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
    Route::resource('plans', PlanController::class);
    Route::resource('coupons', CouponController::class);
    Route::resource('orders', OrderController::class);
    Route::resource('subscriptions', SubscriptionController::class);

    // RBAC Roles (Explicit actions matching RoleController)
    Route::resource('roles', RoleController::class)->except(['show']);

    // Super Admin Routes
    Route::middleware([SuperAdminMiddleware::class])->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'store'])->name('settings.store');
        Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
        Route::post('/translations', [TranslationController::class, 'store'])->name('translations.store');
    });

    // Modules
    Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::post('/modules/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');

    // Settings (Phase 7 & 8)
    Route::get('/settings/email-templates', [EmailTemplateController::class, 'index'])->name('settings.email-templates.index');
    Route::post('/settings/email-templates', [EmailTemplateController::class, 'store'])->name('settings.email-templates.store');

    Route::get('/settings/api-tokens', [ApiTokenController::class, 'index'])->name('settings.api-tokens.index');
    Route::post('/settings/api-tokens', [ApiTokenController::class, 'store'])->name('settings.api-tokens.store');
    Route::delete('/settings/api-tokens/{id}', [ApiTokenController::class, 'destroy'])->name('settings.api-tokens.destroy');
});
