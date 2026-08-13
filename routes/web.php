<?php

use App\Http\Controllers\AIAgentChatController;
use App\Http\Controllers\AIAgentChatPageController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BankTransferPaymentController;
use App\Http\Controllers\Domain\Auth\RoleController;
use App\Http\Controllers\Domain\SaaS\CouponController;
use App\Http\Controllers\Domain\SaaS\OrderController;
use App\Http\Controllers\Domain\SaaS\PlanController;
use App\Http\Controllers\Domain\SaaS\SubscriptionController;
use App\Http\Controllers\HelpdeskCategoryController;
use App\Http\Controllers\HelpdeskReplyController;
use App\Http\Controllers\HelpdeskTicketController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MultiTenancy\MemberController;
use App\Http\Controllers\MultiTenancy\WorkspaceController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesProposalController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\EmailTemplateController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\SettingController;
use App\Http\Controllers\SuperAdmin\TranslationController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WarehouseController;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Route;

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
    Route::get('/dashboard', [HomeController::class, 'Dashboard'])->name('dashboard');

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
    Route::get('plans/{plan}/subscribe', [PlanController::class, 'subscribe'])->name('plans.subscribe');
    Route::post('plans/{plan}/start-trial', [PlanController::class, 'startTrial'])->name('plans.start-trial');
    Route::post('plans/{plan}/assign-free', [PlanController::class, 'assignFreePlan'])->name('plans.assign-free');
    Route::post('plans/apply-coupon', [PlanController::class, 'applyCoupon'])->name('plans.apply-coupon');
    Route::resource('coupons', CouponController::class);
    Route::resource('orders', OrderController::class);
    Route::resource('subscriptions', SubscriptionController::class);
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    // Bank Transfer Payment routes
    Route::get('bank-transfer', [BankTransferPaymentController::class, 'index'])->name('bank-transfer.index');
    Route::post('bank-transfer', [BankTransferPaymentController::class, 'store'])->name('payment.bank-transfer.store');
    Route::post('bank-transfer/update/{id}', [BankTransferPaymentController::class, 'update'])->name('bank-transfer.update');
    Route::post('bank-transfer/{payment}/reject', [BankTransferPaymentController::class, 'reject'])->name('bank-transfer.reject');
    Route::delete('bank-transfer/{payment}', [BankTransferPaymentController::class, 'destroy'])->name('bank-transfer.destroy');

    // Sales & Procurement Routes
    Route::resource('warehouses', WarehouseController::class);
    Route::resource('transfers', TransferController::class)->except(['edit', 'update']);

    // Purchase Invoices
    Route::resource('purchase-invoices', PurchaseInvoiceController::class);
    Route::post('purchase-invoices/{purchaseInvoice}/post', [PurchaseInvoiceController::class, 'post'])->name('purchase-invoices.post');
    Route::get('purchase-invoices/{purchaseInvoice}/print', [PurchaseInvoiceController::class, 'print'])->name('purchase-invoices.print');

    // Sales Invoices
    Route::resource('sales-invoices', SalesInvoiceController::class);
    Route::post('sales-invoices/{salesInvoice}/post', [SalesInvoiceController::class, 'post'])->name('sales-invoices.post');
    Route::get('sales-invoices/{salesInvoice}/print', [SalesInvoiceController::class, 'print'])->name('sales-invoices.print');
    Route::get('sales-invoices/warehouse/products', [SalesInvoiceController::class, 'getWarehouseProducts'])->name('sales-invoices.warehouse.products');
    Route::get('sales-invoices/services/list', [SalesInvoiceController::class, 'getServices'])->name('sales-invoices.services');

    // Purchase Returns
    Route::get('purchase-returns', [PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
    Route::get('purchase-returns/create', [PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
    Route::post('purchase-returns', [PurchaseReturnController::class, 'store'])->name('purchase-returns.store');
    Route::get('purchase-returns/{return}', [PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
    Route::delete('purchase-returns/{return}', [PurchaseReturnController::class, 'destroy'])->name('purchase-returns.destroy');
    Route::post('purchase-returns/{return}/approve', [PurchaseReturnController::class, 'approve'])->name('purchase-returns.approve');
    Route::post('purchase-returns/{return}/complete', [PurchaseReturnController::class, 'complete'])->name('purchase-returns.complete');

    // Sales Returns
    Route::get('sales-returns', [SalesReturnController::class, 'index'])->name('sales-returns.index');
    Route::get('sales-returns/create', [SalesReturnController::class, 'create'])->name('sales-returns.create');
    Route::post('sales-returns', [SalesReturnController::class, 'store'])->name('sales-returns.store');
    Route::get('sales-returns/{salesReturn}', [SalesReturnController::class, 'show'])->name('sales-returns.show');
    Route::delete('sales-returns/{salesReturn}', [SalesReturnController::class, 'destroy'])->name('sales-returns.destroy');
    Route::post('sales-returns/{salesReturn}/approve', [SalesReturnController::class, 'approve'])->name('sales-returns.approve');
    Route::post('sales-returns/{salesReturn}/complete', [SalesReturnController::class, 'complete'])->name('sales-returns.complete');

    // Sales Proposals
    Route::resource('sales-proposals', SalesProposalController::class);
    Route::get('sales-proposals/{salesProposal}/print', [SalesProposalController::class, 'print'])->name('sales-proposals.print');
    Route::post('sales-proposals/{salesProposal}/sent', [SalesProposalController::class, 'sent'])->name('sales-proposals.sent');
    Route::post('sales-proposals/{salesProposal}/accept', [SalesProposalController::class, 'accept'])->name('sales-proposals.accept');
    Route::post('sales-proposals/{salesProposal}/reject', [SalesProposalController::class, 'reject'])->name('sales-proposals.reject');
    Route::post('sales-proposals/{salesProposal}/convert-to-invoice', [SalesProposalController::class, 'convertToInvoice'])->name('sales-proposals.convert-to-invoice');
    Route::get('sales-proposals/warehouse/products', [SalesProposalController::class, 'getWarehouseProducts'])->name('sales-proposals.warehouse.products');
    Route::get('sales-proposals/services/list', [SalesProposalController::class, 'getServices'])->name('sales-proposals.services');

    // Helpdesk
    Route::resource('helpdesk-tickets', HelpdeskTicketController::class);
    Route::get('helpdesk-tickets/today', [HelpdeskTicketController::class, 'today'])->name('helpdesk-tickets.today');
    Route::resource('helpdesk-categories', HelpdeskCategoryController::class);
    Route::post('helpdesk-tickets/{ticket}/replies', [HelpdeskReplyController::class, 'store'])->name('helpdesk-replies.store');
    Route::delete('helpdesk-replies/{reply}', [HelpdeskReplyController::class, 'destroy'])->name('helpdesk-replies.destroy');

    // Media Library
    Route::get('media/page', [MediaController::class, 'page'])->name('media.page');
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media/batch-store', [MediaController::class, 'batchStore'])->name('media.batch-store');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('media/directories', [MediaController::class, 'createDirectory'])->name('media.directories.create');
    Route::put('media/directories/{directory}', [MediaController::class, 'updateDirectory'])->name('media.directories.update');
    Route::delete('media/directories/{directory}', [MediaController::class, 'destroyDirectory'])->name('media.directories.destroy');
    Route::post('media/update-directory', [MediaController::class, 'updateMediaDirectory'])->name('media.update-directory');

    // Messenger Communications
    Route::get('chats', [MessengerController::class, 'index'])->name('chats.index');
    Route::post('chats/send', [MessengerController::class, 'send'])->name('chats.send');
    Route::get('chats/get-contacts', [MessengerController::class, 'getContacts'])->name('chats.get-contacts');
    Route::get('chats/get-messages', [MessengerController::class, 'getMessages'])->name('chats.get-messages');
    Route::post('chats/toggle-favorite', [MessengerController::class, 'toggleFavorite'])->name('chats.toggle-favorite');
    Route::get('chats/get-favorites', [MessengerController::class, 'getFavorites'])->name('chats.get-favorites');
    Route::post('chats/edit-message', [MessengerController::class, 'editMessage'])->name('chats.edit-message');
    Route::post('chats/delete-message', [MessengerController::class, 'deleteMessage'])->name('chats.delete-message');
    Route::post('chats/set-offline', [MessengerController::class, 'setOffline'])->name('chats.set-offline');
    Route::post('chats/update-presence', [MessengerController::class, 'updatePresence'])->name('chats.update-presence');
    Route::get('chats/online-users', [MessengerController::class, 'getOnlineUsers'])->name('chats.online-users');
    Route::post('chats/toggle-pin', [MessengerController::class, 'togglePin'])->name('chats.toggle-pin');
    Route::get('chats/get-pinned', [MessengerController::class, 'getPinned'])->name('chats.get-pinned');
    Route::get('chats/check-new-messages', [MessengerController::class, 'checkNewMessages'])->name('chats.check-new-messages');

    // AI Assistant
    Route::get('ai-agent/chat', [AIAgentChatPageController::class, 'index'])->name('ai-agent.chat');
    Route::get('ai-agent/chat/sessions', [AIAgentChatPageController::class, 'getSessions'])->name('ai-agent.chat.sessions');
    Route::post('ai-agent/chat/session', [AIAgentChatPageController::class, 'createSession'])->name('ai-agent.chat.session.create');
    Route::delete('ai-agent/chat/session/{session}', [AIAgentChatPageController::class, 'destroySession'])->name('ai-agent.chat.session.destroy');
    Route::get('ai-agent/chat/messages/{session}', [AIAgentChatPageController::class, 'getMessages'])->name('ai-agent.chat.messages');
    Route::post('ai-agent/chat', [AIAgentChatController::class, 'chat'])->name('ai-agent.chat.send');

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
