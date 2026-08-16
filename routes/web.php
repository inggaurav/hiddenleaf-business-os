<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AIAgentChatController;
use App\Http\Controllers\AIAgentChatPageController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Automation\AutomationRuleController;
use App\Http\Controllers\Automation\MrFoxMissionController;
use App\Http\Controllers\BankTransferPaymentController;
use App\Http\Controllers\CommandCenter\ApprovalCenterController;
use App\Http\Controllers\CommandCenter\CommandCenterController;
use App\Http\Controllers\Communications\CommunicationWebhookController;
use App\Http\Controllers\Communications\UnifiedInboxController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DatabaseNotificationController;
use App\Http\Controllers\Diagnostics\HealthCheckController;
use App\Http\Controllers\Domain\Auth\RoleController;
use App\Http\Controllers\Domain\SaaS\CouponController;
use App\Http\Controllers\Domain\SaaS\OrderController;
use App\Http\Controllers\Domain\SaaS\PlanController;
use App\Http\Controllers\Domain\SaaS\SubscriptionController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\HelpdeskCategoryController;
use App\Http\Controllers\HelpdeskReplyController;
use App\Http\Controllers\HelpdeskTicketController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HrmController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MrFox\MrFoxChatController;
use App\Http\Controllers\MultiTenancy\MemberController;
use App\Http\Controllers\MultiTenancy\WorkspaceController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\POS\PosBillingCounterController;
use App\Http\Controllers\POS\PosController;
use App\Http\Controllers\POS\PosDashboardController;
use App\Http\Controllers\POS\PosDiscountController;
use App\Http\Controllers\POS\PosReportController;
use App\Http\Controllers\POS\PosReturnController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesProposalController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\SettingController;
use App\Http\Controllers\SuperAdmin\TranslationController;
use App\Http\Controllers\TasklyController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'setup'])->name('install.setup');
Route::post('/install/test-db', [InstallController::class, 'testDatabase'])->name('install.test-db');
Route::post('/install/license/validate', [InstallController::class, 'validateLicense'])
    ->middleware('throttle:10,1')
    ->name('install.license.validate');
Route::get('/site/{slug}', [LandingPageController::class, 'publicSite'])->name('landing.public');
Route::get('/site/{slug}/{page}', [LandingPageController::class, 'publicPage'])->name('landing.page');

// Public Diagnostics & Health Endpoints
Route::get('/health/live', [HealthCheckController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthCheckController::class, 'ready'])->name('health.ready');

// Public Communications Webhook Endpoint
Route::match(['get', 'post'], 'api/v1/webhooks/communications/{provider}', [CommunicationWebhookController::class, 'handle'])
    ->name('webhooks.communications')
    ->withoutMiddleware([VerifyCsrfToken::class, EnsureTenantContext::class]);

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
    Route::middleware(SuperAdminMiddleware::class)->prefix('update')->name('update.')->group(function () {
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('/check', [UpdateController::class, 'check'])->middleware('throttle:10,1')->name('check');
        Route::post('/', [UpdateController::class, 'update'])->middleware('throttle:3,10')->name('run');
        Route::post('/{history}/rollback', [UpdateController::class, 'rollback'])->middleware('throttle:3,10')->name('rollback');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::put('/webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
    Route::patch('/webhooks/{webhook}/toggle', [WebhookController::class, 'toggle'])->name('webhooks.toggle');
    Route::post('/webhooks/{webhook}/rotate-secret', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
    Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test'])->name('webhooks.test');
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

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
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/leave-impersonation', [UserController::class, 'leaveImpersonation'])->name('users.leave-impersonation');
    Route::post('/users/{user}/assign-plan', [UserController::class, 'assignPlan'])->name('users.assign-plan');
    Route::get('/users-login-history', [UserController::class, 'loginHistory'])->name('users.login-history');

    // SaaS Routes
    Route::resource('plans', PlanController::class);
    Route::get('plans/{plan}/subscribe', [PlanController::class, 'subscribe'])->name('plans.subscribe');
    Route::post('plans/{plan}/start-trial', [PlanController::class, 'startTrial'])->name('plans.start-trial');
    Route::post('plans/{plan}/assign-free', [PlanController::class, 'assignFreePlan'])->name('plans.assign-free');
    Route::post('plans/apply-coupon', [PlanController::class, 'applyCoupon'])->name('plans.apply-coupon');
    Route::resource('coupons', CouponController::class);
    Route::resource('orders', OrderController::class)->only(['index', 'show', 'destroy']);
    Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'store']);
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    // Bank Transfer Payment routes
    Route::get('bank-transfer', [BankTransferPaymentController::class, 'index'])->name('bank-transfer.index');
    Route::post('bank-transfer', [BankTransferPaymentController::class, 'store'])->name('payment.bank-transfer.store');
    Route::post('bank-transfer/update/{id}', [BankTransferPaymentController::class, 'update'])->name('bank-transfer.update');
    Route::post('bank-transfer/{payment}/reject', [BankTransferPaymentController::class, 'reject'])->name('bank-transfer.reject');
    Route::delete('bank-transfer/{payment}', [BankTransferPaymentController::class, 'destroy'])->name('bank-transfer.destroy');

    // Sales & Procurement Routes
    // Accounting Module Routes
    Route::middleware('module.status:account')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [AccountingController::class, 'dashboard'])->name('index');
        Route::get('dashboard', [AccountingController::class, 'dashboard'])->name('dashboard');
        Route::get('accounts', [AccountingController::class, 'accounts'])->name('accounts');
        Route::post('types', [AccountingController::class, 'storeType'])->name('types.store');
        Route::post('accounts', [AccountingController::class, 'storeAccount'])->name('accounts.store');
        Route::get('journals', [AccountingController::class, 'journals'])->name('journals');
        Route::post('journals', [AccountingController::class, 'storeJournal'])->name('journals.store');
        Route::post('journals/{entry}/post', [AccountingController::class, 'postJournal'])->name('journals.post');
        Route::get('reports', [AccountingController::class, 'reports'])->name('reports');
        Route::post('bank-transfers', [AccountingController::class, 'bankTransfer'])->name('bank-transfers.store');
        Route::post('bank-reconciliations', [AccountingController::class, 'reconcile'])->name('bank-reconciliations.store');

        // Customers & Vendors
        Route::get('customers', [AccountingController::class, 'customers'])->name('customers.index');
        Route::post('customers', [AccountingController::class, 'storeCustomer'])->name('customers.store');
        Route::put('customers/{customer}', [AccountingController::class, 'updateCustomer'])->name('customers.update');
        Route::delete('customers/{customer}', [AccountingController::class, 'destroyCustomer'])->name('customers.destroy');

        Route::get('vendors', [AccountingController::class, 'vendors'])->name('vendors.index');
        Route::post('vendors', [AccountingController::class, 'storeVendor'])->name('vendors.store');
        Route::put('vendors/{vendor}', [AccountingController::class, 'updateVendor'])->name('vendors.update');
        Route::delete('vendors/{vendor}', [AccountingController::class, 'destroyVendor'])->name('vendors.destroy');

        // Customer & Vendor Payments
        Route::get('customer-payments', [AccountingController::class, 'customerPayments'])->name('customer-payments.index');
        Route::post('customer-payments', [AccountingController::class, 'storeCustomerPayment'])->name('customer-payments.store');

        Route::get('vendor-payments', [AccountingController::class, 'vendorPayments'])->name('vendor-payments.index');
        Route::post('vendor-payments', [AccountingController::class, 'storeVendorPayment'])->name('vendor-payments.store');

        // Revenues & Expenses
        Route::get('revenues', [AccountingController::class, 'revenues'])->name('revenues.index');
        Route::post('revenues', [AccountingController::class, 'storeRevenue'])->name('revenues.store');

        Route::get('expenses', [AccountingController::class, 'expenses'])->name('expenses.index');
        Route::post('expenses', [AccountingController::class, 'storeExpense'])->name('expenses.store');

        // Credit & Debit Notes
        Route::get('credit-notes', [AccountingController::class, 'creditNotes'])->name('credit-notes.index');
        Route::post('credit-notes', [AccountingController::class, 'storeCreditNote'])->name('credit-notes.store');

        Route::get('debit-notes', [AccountingController::class, 'debitNotes'])->name('debit-notes.index');
        Route::post('debit-notes', [AccountingController::class, 'storeDebitNote'])->name('debit-notes.store');
    });
    Route::get('account/dashboard', [AccountingController::class, 'dashboard'])->middleware('module.status:account');
    Route::get('account', [AccountingController::class, 'dashboard'])->middleware('module.status:account');

    Route::middleware('module.status:hrm')->prefix('hrm')->name('hrm.')->group(function () {
        Route::get('/', [HrmController::class, 'index'])->name('index');
        Route::get('dashboard', [HrmController::class, 'dashboard'])->name('dashboard');
        Route::get('employees/list', [HrmController::class, 'index'])->name('employees.list');
        Route::post('structure/{resource}', [HrmController::class, 'storeStructure'])->name('structure.store');
        Route::post('employees', [HrmController::class, 'storeEmployee'])->name('employees.store');
        Route::post('attendance', [HrmController::class, 'attendance'])->name('attendance.store');
        Route::post('leave-types', [HrmController::class, 'storeLeaveType'])->name('leave-types.store');
        Route::post('leaves', [HrmController::class, 'requestLeave'])->name('leaves.store');
        Route::post('leaves/{leave}/review', [HrmController::class, 'reviewLeave'])->name('leaves.review');
        Route::post('salary-components', [HrmController::class, 'storeComponent'])->name('salary-components.store');
        Route::post('salary-components/assign', [HrmController::class, 'assignComponent'])->name('salary-components.assign');
        Route::post('payslips', [HrmController::class, 'generatePayslip'])->name('payslips.store');
        Route::post('payslips/{payslip}/pay', [HrmController::class, 'payPayslip'])->name('payslips.pay');
        Route::post('appraisals', [HrmController::class, 'appraisal'])->name('appraisals.store');
        Route::post('documents', [HrmController::class, 'uploadDocument'])->name('documents.store');
        Route::get('documents/{document}/download', [HrmController::class, 'downloadDocument'])->name('documents.download');
    });

    Route::middleware('module.status:lead')->prefix('crm')->name('crm.')->group(function () {
        Route::get('/', [CrmController::class, 'index'])->name('index');
        Route::get('dashboard', [CrmController::class, 'dashboard'])->name('dashboard');
        Route::get('leads/list', [CrmController::class, 'index'])->name('leads.list');
        Route::middleware('workspace.permission:crm.manage')->group(function () {
            Route::post('pipelines', [CrmController::class, 'storePipeline'])->name('pipelines.store');
            Route::post('leads', [CrmController::class, 'storeLead'])->name('leads.store');
            Route::post('leads/{leadId}/move', [CrmController::class, 'moveLead'])->name('leads.move');
            Route::post('leads/{leadId}/convert', [CrmController::class, 'convertLead'])->name('leads.convert');
            Route::post('deals/{dealId}/move', [CrmController::class, 'moveDeal'])->name('deals.move');
            Route::post('webforms', [CrmController::class, 'storeWebform'])->name('webforms.store');
            Route::post('{type}/{id}/notes', [CrmController::class, 'addNote'])->name('notes.store');
            Route::post('{type}/{id}/activities', [CrmController::class, 'addActivity'])->name('activities.store');
        });
    });
    Route::post('crm/forms/{token}/submit', [CrmController::class, 'publicWebformSubmit'])->name('crm.webforms.submit')->withoutMiddleware([EnsureTenantContext::class]);

    Route::middleware('module.status:taskly')->prefix('taskly')->name('taskly.')->group(function () {
        Route::get('/', [TasklyController::class, 'index'])->name('index');
        Route::get('dashboard', [TasklyController::class, 'dashboard'])->name('dashboard');
        Route::get('projects/list', [TasklyController::class, 'index'])->name('projects.list');
        Route::middleware('workspace.permission:taskly.manage')->group(function () {
        Route::post('projects', [TasklyController::class, 'storeProject'])->name('projects.store');
        Route::post('tasks', [TasklyController::class, 'storeTask'])->name('tasks.store');
        Route::post('tasks/{task}/move', [TasklyController::class, 'moveTask'])->name('tasks.move');
        Route::post('tasks/{task}/comments', [TasklyController::class, 'comment'])->name('comments.store');
        Route::post('milestones', [TasklyController::class, 'milestone'])->name('milestones.store');
        Route::post('timesheets', [TasklyController::class, 'timesheet'])->name('timesheets.store');
        Route::post('timesheets/{timesheet}/approve', [TasklyController::class, 'approveTime'])->name('timesheets.approve');
        Route::post('issues', [TasklyController::class, 'issue'])->name('issues.store');
        });
    });
    Route::get('projects/dashboard', [TasklyController::class, 'dashboard'])->middleware('module.status:taskly');

    // POS Module Routes (31 routes + aliases)
    Route::middleware(['module.status:pos', 'pos.permission'])->group(function () {
        Route::get('/pos', [PosDashboardController::class, 'index'])->name('pos');
        Route::get('/pos/dashboard', [PosDashboardController::class, 'index'])->name('pos.index');
        Route::get('/pos/terminal', [PosController::class, 'create'])->name('pos.terminal');

        // POS Routes
        Route::get('/pos/orders', [PosController::class, 'index'])->name('pos.orders');
        Route::get('/pos/create', [PosController::class, 'create'])->name('pos.create');
        Route::get('/pos/products', [PosController::class, 'getProducts'])->name('pos.products');
        Route::get('/pos/pos-number', [PosController::class, 'getNextPosNumber'])->name('pos.pos-number');
        Route::post('/pos/store', [PosController::class, 'store'])->name('pos.store');
        Route::get('/pos/orders/{sale}', [PosController::class, 'show'])->name('pos.show');
        Route::get('/pos/barcode', [PosController::class, 'barcode'])->name('pos.barcode');
        Route::get('/pos/barcode/{sale}', [PosController::class, 'printBarcode'])->name('pos.barcode.print');
        Route::get('/pos/orders/{sale}/print', [PosController::class, 'print'])->name('pos-orders.print');

        // POS Billing Counter
        Route::get('/pos/billing-counters', [PosBillingCounterController::class, 'index'])->name('pos.billing-counters');
        Route::post('/pos/billing-counters', [PosBillingCounterController::class, 'store'])->name('pos.billing-counters.store');
        Route::put('/pos/billing-counters/{pos_billing_counter}', [PosBillingCounterController::class, 'update'])->name('pos.billing-counters.update');
        Route::delete('/pos/billing-counters/{pos_billing_counter}', [PosBillingCounterController::class, 'destroy'])->name('pos.billing-counters.destroy');

        // POS Discounts
        Route::get('/pos/discounts', [PosDiscountController::class, 'index'])->name('pos.discounts.index');
        Route::get('/pos/discounts/create', [PosDiscountController::class, 'create'])->name('pos.discounts.create');
        Route::post('/pos/discounts', [PosDiscountController::class, 'store'])->name('pos.discounts.store');
        Route::get('/pos/discounts/{pos_discount}', [PosDiscountController::class, 'show'])->name('pos.discounts.show');
        Route::get('/pos/discounts/{pos_discount}/edit', [PosDiscountController::class, 'edit'])->name('pos.discounts.edit');
        Route::put('/pos/discounts/{pos_discount}', [PosDiscountController::class, 'update'])->name('pos.discounts.update');
        Route::delete('/pos/discounts/{pos_discount}', [PosDiscountController::class, 'destroy'])->name('pos.discounts.destroy');

        // POS Reports
        Route::prefix('pos/reports')->name('pos.reports.')->group(function () {
            Route::get('/sales', [PosReportController::class, 'sales'])->name('sales');
            Route::get('/products', [PosReportController::class, 'products'])->name('products');
            Route::get('/customers', [PosReportController::class, 'customers'])->name('customers');
        });

        // POS Returns
        Route::prefix('pos/returns')->name('pos.returns.')->group(function () {
            Route::get('/', [PosReturnController::class, 'index'])->name('index');
            Route::get('/create', [PosReturnController::class, 'create'])->name('create');
            Route::post('/', [PosReturnController::class, 'store'])->name('store');
            Route::get('/{posReturn}', [PosReturnController::class, 'show'])->name('show');
            Route::post('/{posReturn}/approve', [PosReturnController::class, 'approve'])->name('approve');
            Route::post('/{posReturn}/complete', [PosReturnController::class, 'complete'])->name('complete');
            Route::delete('/{posReturn}', [PosReturnController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware('module.status:landingpage')->prefix('landing')->name('landing.')->group(function () {
        Route::get('/', [LandingPageController::class, 'manage'])->name('manage');
        Route::post('sites', [LandingPageController::class, 'storeSite'])->name('sites.store');
        Route::post('sites/{site}/sections', [LandingPageController::class, 'section'])->name('sections.store');
        Route::post('sites/{site}/pages', [LandingPageController::class, 'page'])->name('pages.store');
        Route::post('sites/{site}/publish', [LandingPageController::class, 'publish'])->name('publish');
    });

    Route::middleware('module.status:productservice')->prefix('product-service')->name('product-service.')->group(function () {
        Route::get('dashboard', [ProductServiceController::class, 'dashboard'])->name('dashboard');
        Route::get('stock', [ProductServiceController::class, 'stockIndex'])->name('stock.index');
        Route::post('stock', [ProductServiceController::class, 'adjust'])->name('stock.store');
        Route::get('categories', [ProductServiceController::class, 'categoriesIndex'])->name('categories.index');
        Route::post('categories', [ProductServiceController::class, 'storeCategory'])->name('categories.store');
        Route::put('categories/{category}', [ProductServiceController::class, 'updateCategory'])->name('categories.update');
        Route::delete('categories/{category}', [ProductServiceController::class, 'destroyCategory'])->name('categories.destroy');
        Route::get('units', [ProductServiceController::class, 'unitsIndex'])->name('units.index');
        Route::post('units', [ProductServiceController::class, 'storeUnit'])->name('units.store');
        Route::put('units/{unit}', [ProductServiceController::class, 'updateUnit'])->name('units.update');
        Route::delete('units/{unit}', [ProductServiceController::class, 'destroyUnit'])->name('units.destroy');
        Route::get('taxes', [ProductServiceController::class, 'taxesIndex'])->name('taxes.index');
        Route::post('taxes', [ProductServiceController::class, 'storeTax'])->name('taxes.store');
        Route::put('taxes/{tax}', [ProductServiceController::class, 'updateTax'])->name('taxes.update');
        Route::delete('taxes/{tax}', [ProductServiceController::class, 'destroyTax'])->name('taxes.destroy');
        Route::post('{productService}/adjust-stock', [ProductServiceController::class, 'adjust'])->name('adjust-stock');
    });
    Route::get('inventory/dashboard', [ProductServiceController::class, 'dashboard'])->middleware('module.status:productservice')->name('inventory.dashboard');
    Route::get('inventory/movements', [ProductServiceController::class, 'movements'])->middleware('module.status:productservice')->name('inventory.movements');
    Route::get('api/product-service/items', [ProductServiceController::class, 'apiIndex'])->middleware('module.status:productservice')->name('api.product-service.items.index');

    Route::resource('product-service', ProductServiceController::class)
        ->middleware('module.status:productservice');
    Route::resource('warehouses', WarehouseController::class);
    Route::resource('transfers', TransferController::class)->except(['edit', 'update']);

    // Purchase Invoices
    Route::get('procurement/dashboard', [PurchaseInvoiceController::class, 'dashboard'])->name('procurement.dashboard');
    Route::get('purchase-invoices-dashboard', [PurchaseInvoiceController::class, 'dashboard'])->name('purchase-invoices.dashboard');
    Route::resource('purchase-invoices', PurchaseInvoiceController::class);
    Route::post('purchase-invoices/{purchaseInvoice}/post', [PurchaseInvoiceController::class, 'post'])->name('purchase-invoices.post');
    Route::get('purchase-invoices/{purchaseInvoice}/print', [PurchaseInvoiceController::class, 'print'])->name('purchase-invoices.print');

    // Sales Invoices
    Route::get('sales/dashboard', [SalesInvoiceController::class, 'dashboard'])->name('sales.dashboard');
    Route::get('sales-invoices-dashboard', [SalesInvoiceController::class, 'dashboard'])->name('sales-invoices.dashboard');
    Route::get('sales-invoices/warehouse/products', [SalesInvoiceController::class, 'getWarehouseProducts'])->name('sales-invoices.warehouse.products');
    Route::get('sales-invoices/services/list', [SalesInvoiceController::class, 'getServices'])->name('sales-invoices.services');
    Route::resource('sales-invoices', SalesInvoiceController::class);
    Route::post('sales-invoices/{salesInvoice}/post', [SalesInvoiceController::class, 'post'])->name('sales-invoices.post');
    Route::get('sales-invoices/{salesInvoice}/print', [SalesInvoiceController::class, 'print'])->name('sales-invoices.print');

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
    Route::get('sales-proposals/warehouse/products', [SalesProposalController::class, 'getWarehouseProducts'])->name('sales-proposals.warehouse.products');
    Route::get('sales-proposals/services/list', [SalesProposalController::class, 'getServices'])->name('sales-proposals.services');
    Route::resource('sales-proposals', SalesProposalController::class);
    Route::get('sales-proposals/{salesProposal}/print', [SalesProposalController::class, 'print'])->name('sales-proposals.print');
    Route::post('sales-proposals/{salesProposal}/sent', [SalesProposalController::class, 'sent'])->name('sales-proposals.sent');
    Route::post('sales-proposals/{salesProposal}/accept', [SalesProposalController::class, 'accept'])->name('sales-proposals.accept');
    Route::post('sales-proposals/{salesProposal}/reject', [SalesProposalController::class, 'reject'])->name('sales-proposals.reject');
    Route::post('sales-proposals/{salesProposal}/convert-to-invoice', [SalesProposalController::class, 'convertToInvoice'])->name('sales-proposals.convert-to-invoice');

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
    Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');
    Route::get('media/{media}/preview', [MediaController::class, 'preview'])->name('media.preview');
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
    Route::post('chats/toggle-message-pin', [MessengerController::class, 'toggleMessagePin'])->name('chats.toggle-message-pin');
    Route::get('chats/get-pinned', [MessengerController::class, 'getPinned'])->name('chats.get-pinned');
    Route::get('chats/check-new-messages', [MessengerController::class, 'checkNewMessages'])->name('chats.check-new-messages');

    // AI Assistant
    Route::get('ai-agent/chat', [AIAgentChatPageController::class, 'index'])->name('ai-agent.chat');
    Route::get('ai-agent/chat/sessions', [AIAgentChatPageController::class, 'getSessions'])->name('ai-agent.chat.sessions');
    Route::post('ai-agent/chat/session', [AIAgentChatPageController::class, 'createSession'])->name('ai-agent.chat.session.create');
    Route::delete('ai-agent/chat/session/{session}', [AIAgentChatPageController::class, 'destroySession'])->name('ai-agent.chat.session.destroy');
    Route::patch('ai-agent/chat/session/{session}/archive', [AIAgentChatPageController::class, 'archiveSession'])->name('ai-agent.chat.session.archive');
    Route::get('ai-agent/chat/messages/{session}', [AIAgentChatPageController::class, 'getMessages'])->name('ai-agent.chat.messages');
    Route::post('ai-agent/chat', [AIAgentChatController::class, 'chat'])->name('ai-agent.chat.send');

    // Mr. Fox Intelligence & Action Agent API
    Route::prefix('api/v1/mr-fox')->name('mr-fox.')->middleware('throttle:60,1')->group(function () {
        Route::post('chat', [MrFoxChatController::class, 'chat'])->name('chat');
        Route::get('insights', [MrFoxChatController::class, 'getInsights'])->name('insights');
        Route::get('conversations', [MrFoxChatController::class, 'getConversations'])->name('conversations');
        Route::get('conversations/{id}', [MrFoxChatController::class, 'getConversationMessages'])->name('conversations.show');
        Route::post('actions/{id}/approve', [MrFoxChatController::class, 'approveAction'])->name('actions.approve');
        Route::post('actions/{id}/reject', [MrFoxChatController::class, 'rejectAction'])->name('actions.reject');
    });

    // Unified Communications Inbox
    Route::get('inbox', [UnifiedInboxController::class, 'index'])->name('inbox.index');
    Route::prefix('api/v1/communications')->name('communications.')->group(function () {
        Route::get('conversations', [UnifiedInboxController::class, 'getConversations'])->name('conversations');
        Route::get('conversations/{id}', [UnifiedInboxController::class, 'getThread'])->name('thread');
        Route::post('conversations/{id}/reply', [UnifiedInboxController::class, 'reply'])->name('reply');
        Route::post('conversations/{id}/draft', [UnifiedInboxController::class, 'draftReply'])->name('draft');
        Route::post('conversations/{id}/link-crm', [UnifiedInboxController::class, 'linkCrm'])->name('link-crm');
    });

    // Automations & Missions
    Route::get('automations', [AutomationRuleController::class, 'index'])->name('automations.index');
    Route::post('automations', [AutomationRuleController::class, 'store'])->name('automations.store');
    Route::patch('automations/{id}/toggle', [AutomationRuleController::class, 'toggle'])->name('automations.toggle');
    Route::get('automations/{id}/runs', [AutomationRuleController::class, 'getRuns'])->name('automations.runs');

    Route::get('missions', [MrFoxMissionController::class, 'index'])->name('missions.index');
    Route::post('missions', [MrFoxMissionController::class, 'create'])->name('missions.store');
    Route::get('missions/{id}', [MrFoxMissionController::class, 'show'])->name('missions.show');
    Route::post('missions/{id}/step', [MrFoxMissionController::class, 'executeStep'])->name('missions.step');
    Route::post('missions/{id}/control', [MrFoxMissionController::class, 'control'])->name('missions.control');

    // Mr. Fox Executive Command Center & Approvals
    Route::get('command-center', [CommandCenterController::class, 'index'])->name('command-center.index');
    Route::get('command-center/health', [CommandCenterController::class, 'health'])->name('command-center.health');
    Route::get('command-center/priorities', [CommandCenterController::class, 'priorities'])->name('command-center.priorities');
    Route::get('command-center/briefing', [CommandCenterController::class, 'briefing'])->name('command-center.briefing');
    Route::get('command-center/activity', [CommandCenterController::class, 'activity'])->name('command-center.activity');
    Route::get('command-center/search', [CommandCenterController::class, 'search'])->name('command-center.search');
    Route::get('command-center/approvals', [ApprovalCenterController::class, 'index'])->name('command-center.approvals.index');
    Route::post('command-center/approvals/{id}/approve', [ApprovalCenterController::class, 'approve'])->name('command-center.approvals.approve');
    Route::post('command-center/approvals/{id}/reject', [ApprovalCenterController::class, 'reject'])->name('command-center.approvals.reject');

    // Customer Onboarding Wizard & Demo Data
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/step', [OnboardingController::class, 'updateStep'])->name('onboarding.step');
    Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('onboarding/demo-data/load', [OnboardingController::class, 'loadDemoData'])->name('onboarding.demo.load');
    Route::post('onboarding/demo-data/reset', [OnboardingController::class, 'resetDemoData'])->name('onboarding.demo.reset');

    // General & System Settings
    Route::get('settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [App\Http\Controllers\SettingController::class, 'store'])->name('settings.store');
    Route::post('settings/brand', [App\Http\Controllers\SettingController::class, 'saveBrandSettings'])->name('settings.brand.store');
    Route::post('settings/email', [App\Http\Controllers\SettingController::class, 'saveEmailSettings'])->name('settings.email.store');
    Route::post('settings/test-mail', [App\Http\Controllers\SettingController::class, 'sendTestMail'])->name('settings.test-mail');
    Route::post('settings/storage', [App\Http\Controllers\SettingController::class, 'saveStorageSettings'])->name('settings.storage.store');
    Route::post('settings/bank-transfer', [App\Http\Controllers\SettingController::class, 'saveBankTransferSettings'])->name('settings.bank-transfer.store');
    Route::post('settings/currency', [App\Http\Controllers\SettingController::class, 'saveCurrencySettings'])->name('settings.currency.store');
    Route::post('settings/cookie', [App\Http\Controllers\SettingController::class, 'saveCookieSettings'])->name('settings.cookie.store');
    Route::post('settings/cache-clear', [App\Http\Controllers\SettingController::class, 'clearCache'])->name('settings.cache.clear');

    // Localization / Languages
    Route::resource('languages', LanguageController::class);
    Route::get('languages/change/{lang}', [LanguageController::class, 'changeLang'])->name('languages.change');
    Route::post('languages/save-data/{lang}', [LanguageController::class, 'saveLanguageData'])->name('languages.save-data');

    // Email Templates
    Route::resource('email-templates', EmailTemplateController::class)->only(['index', 'show', 'store', 'update']);
    Route::post('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
    Route::post('email-templates/{emailTemplate}/reset', [EmailTemplateController::class, 'reset'])->name('email-templates.reset');
    Route::get('settings/email-templates', [EmailTemplateController::class, 'index'])->name('settings.email-templates.index');
    Route::post('settings/email-templates', [EmailTemplateController::class, 'store'])->name('settings.email-templates.store');

    // Notification Templates
    Route::resource('notification-templates', NotificationTemplateController::class)->only(['index', 'show', 'update']);
    Route::post('notification-templates/{notificationTemplate}/preview', [NotificationTemplateController::class, 'preview'])->name('notification-templates.preview');
    Route::post('notification-templates/{notificationTemplate}/reset', [NotificationTemplateController::class, 'reset'])->name('notification-templates.reset');
    Route::get('notifications', [DatabaseNotificationController::class, 'index'])->name('notifications.index');
    Route::match(['post', 'patch'], 'notifications/read-all', [DatabaseNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::match(['post', 'patch'], 'notifications/{notification}/read', [DatabaseNotificationController::class, 'markRead'])->name('notifications.read');

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
    Route::post('/modules/install', [ModuleController::class, 'install'])->name('modules.install');

    // Settings API Tokens
    Route::get('/settings/api-tokens', [ApiTokenController::class, 'index'])->name('settings.api-tokens.index');
    Route::post('/settings/api-tokens', [ApiTokenController::class, 'store'])->name('settings.api-tokens.store');
    Route::delete('/settings/api-tokens/{id}', [ApiTokenController::class, 'destroy'])->name('settings.api-tokens.destroy');
});
