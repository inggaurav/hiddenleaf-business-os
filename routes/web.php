<?php

use Illuminate\Support\Facades\Route;
use HiddenLeaf\Auth\Controllers\AuthController;
use HiddenLeaf\Auth\Controllers\PasswordController;
use HiddenLeaf\Auth\Controllers\ProfileController;
use HiddenLeaf\Auth\Controllers\UserController;
use HiddenLeaf\Auth\Controllers\VerifyEmailController;
use HiddenLeaf\MultiTenancy\Controllers\WorkspaceController;
use HiddenLeaf\MultiTenancy\Controllers\MemberController;
use HiddenLeaf\Domain\Auth\Controllers\RoleController;
use HiddenLeaf\Domain\SaaS\Controllers\PlanController;
use HiddenLeaf\Domain\SaaS\Controllers\CouponController;
use HiddenLeaf\Domain\SaaS\Controllers\OrderController;
use HiddenLeaf\Domain\SaaS\Controllers\BankTransferController;
use HiddenLeaf\Settings\Controllers\SettingController;
use HiddenLeaf\Kernel\Controllers\ModuleController;
use HiddenLeaf\Storage\Controllers\MediaController;
use HiddenLeaf\Localization\Controllers\TranslationController;
use HiddenLeaf\Notifications\Controllers\NotificationController;
use HiddenLeaf\Notifications\Controllers\EmailTemplateController;
use HiddenLeaf\Admin\Controllers\AdminDashboardController;
use HiddenLeaf\Admin\Controllers\AdminCompanyController;
use HiddenLeaf\Admin\Controllers\AdminHubController;
use HiddenLeaf\Admin\Controllers\AdminAuditController;
use HiddenLeaf\Admin\Controllers\AdminSettingController;
use HiddenLeaf\Installer\Controllers\InstallerController;

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
    Route::get('/verify-email', [VerifyEmailController::class, 'prompt'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])->name('verification.verify');

    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

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

    // RBAC Roles
    Route::resource('roles', RoleController::class);

    // SaaS Plans, Subscriptions, Coupons & Orders
    Route::resource('plans', PlanController::class);
    Route::get('/plans/{plan}/subscribe', [PlanController::class, 'subscribe'])->name('plans.subscribe');
    Route::post('/plans/{plan}/start-trial', [PlanController::class, 'startTrial'])->name('plans.start-trial');
    Route::post('/plans/add-on/update-price', [PlanController::class, 'updateModulePrice'])->name('plans.add-on.update-price');
    Route::post('/plans/apply-coupon', [PlanController::class, 'applyCoupon'])->name('plans.apply-coupon');
    Route::post('/plans/{plan}/assign-free', [PlanController::class, 'assignFreePlan'])->name('plans.assign-free');
    Route::post('/plans/package-settings', [PlanController::class, 'updatePackageSettings'])->name('plans.package-settings.update');
    Route::post('/subscriptions', [PlanController::class, 'storeSubscription'])->name('subscriptions.store');
    Route::resource('orders', OrderController::class)->only(['index', 'show']);
    Route::resource('coupons', CouponController::class);

    // Payments (Bank Transfer Core)
    Route::get('/bank-transfer', [BankTransferController::class, 'index'])->name('bank-transfer.index');
    Route::post('/bank-transfer', [BankTransferController::class, 'store'])->name('bank-transfer.store');
    Route::post('/bank-transfer/update/{id}', [BankTransferController::class, 'update'])->name('bank-transfer.update');
    Route::post('/bank-transfer/{payment}/reject', [BankTransferController::class, 'reject'])->name('bank-transfer.reject');
    Route::delete('/bank-transfer/{payment}', [BankTransferController::class, 'destroy'])->name('bank-transfer.destroy');

    // Super Admin Portal
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [AdminCompanyController::class, 'store'])->name('companies.store');
        Route::get('/companies/{user}/edit', [AdminCompanyController::class, 'edit'])->name('companies.edit');
        Route::patch('/companies/{user}/toggle-status', [AdminCompanyController::class, 'toggleStatus'])->name('companies.toggle-status');
        Route::post('/companies/{user}/impersonate', [AdminCompanyController::class, 'impersonate'])->name('companies.impersonate');
        Route::post('/companies/leave-impersonation', [AdminCompanyController::class, 'leaveImpersonation'])->name('companies.leave-impersonation');
        Route::post('/companies/{user}/assign-plan', [AdminCompanyController::class, 'assignPlan'])->name('companies.assign-plan');
        Route::get('/users/{user}/admin-hub', [AdminHubController::class, 'index'])->name('users.admin-hub');
        Route::get('/users/login/history', [AdminAuditController::class, 'loginHistory'])->name('users.login-history');
    });

    // Settings & Whitelabel Branding
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/brand', [SettingController::class, 'updateBrand'])->name('settings.brand.update');
    Route::post('/settings/company', [SettingController::class, 'updateCompany'])->name('settings.company.update');
    Route::post('/settings/system', [SettingController::class, 'updateSystem'])->name('settings.system.update');
    Route::post('/settings/currency', [SettingController::class, 'updateCurrency'])->name('settings.currency.update');
    Route::post('/settings/cache/clear', [SettingController::class, 'clearCache'])->name('settings.cache.clear');
    Route::post('/settings/optimize', [SettingController::class, 'optimizeSite'])->name('settings.optimize');
    Route::post('/settings/cookie', [SettingController::class, 'updateCookie'])->name('settings.cookie.update');
    Route::post('/settings/seo', [SettingController::class, 'updateSeo'])->name('settings.seo.update');
    Route::post('/settings/storage', [SettingController::class, 'updateStorage'])->name('settings.storage.update');
    Route::post('/settings/email', [SettingController::class, 'updateEmail'])->name('settings.email.update');
    Route::post('/settings/email/test', [SettingController::class, 'testEmail'])->name('settings.email.test');

    // Modules Engine
    Route::get('/add-ons', [ModuleController::class, 'index'])->name('add-ons.index');
    Route::get('/add-on/upload', [ModuleController::class, 'upload'])->name('add-on.upload');
    Route::post('/add-ons/install', [ModuleController::class, 'install'])->name('add-ons.install');
    Route::post('/add-on/{name}/enable', [ModuleController::class, 'enable'])->name('add-on.enable');
    Route::get('/user/active-modules', [ModuleController::class, 'userActive'])->name('user.active-modules');
    Route::delete('/user/active-modules/{id}', [ModuleController::class, 'removeUserActive'])->name('user.active-modules.remove');

    // Media Manager
    Route::get('/media-library', [MediaController::class, 'page'])->name('media-library');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media/batch', [MediaController::class, 'batchStore'])->name('media.batch');
    Route::delete('/media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/media/directories', [MediaController::class, 'createDirectory'])->name('media.directories.create');
    Route::put('/media/directories/{id}', [MediaController::class, 'updateDirectory'])->name('media.directories.update');
    Route::delete('/media/directories/{id}', [MediaController::class, 'destroyDirectory'])->name('media.directories.destroy');

    // Languages & Localization
    Route::get('/languages/manage', [TranslationController::class, 'manage'])->name('languages.manage');
    Route::post('/languages/{locale}/update', [TranslationController::class, 'update'])->name('languages.update');
    Route::post('/languages/create', [TranslationController::class, 'create'])->name('languages.create');
    Route::delete('/languages/{code}', [TranslationController::class, 'delete'])->name('languages.delete');
    Route::patch('/languages/{code}/toggle', [TranslationController::class, 'toggle'])->name('languages.toggle');
    Route::post('/languages/change', [TranslationController::class, 'change'])->name('languages.change');

    // Notifications & Email Templates
    Route::get('/notification-templates', [NotificationController::class, 'index'])->name('notification-templates.index');
    Route::get('/notification-templates/{id}/edit', [NotificationController::class, 'editTemplate'])->name('notification-templates.edit');
    Route::put('/notification-templates/{id}', [NotificationController::class, 'updateTemplate'])->name('notification-templates.update');
    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/email-templates/{id}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::get('/email-templates/{id}/language/{lang}', [EmailTemplateController::class, 'getLanguageContent'])->name('email-templates.language-content');
    Route::put('/email-templates/{id}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::put('/email-templates/{id}/update-meta', [EmailTemplateController::class, 'updateMeta'])->name('email-templates.update-meta');
});

// Web Installer Routes
Route::prefix('install')->name('installer.')->group(function () {
    Route::get('/', [InstallerController::class, 'welcome'])->name('welcome');
    Route::get('/requirements', [InstallerController::class, 'requirements'])->name('requirements');
    Route::get('/environment', [InstallerController::class, 'environment'])->name('environment');
    Route::post('/environment', [InstallerController::class, 'storeEnvironment'])->name('environment.store');
    Route::get('/database', [InstallerController::class, 'database'])->name('database');
    Route::post('/database', [InstallerController::class, 'storeDatabase'])->name('database.store');
    Route::get('/final', [InstallerController::class, 'final'])->name('final');
});
