<?php

$domains = [
    'foundation' => [
        ['tenant-isolation', 'app/Http/Controllers/UserController.php', 'users', 'app/Http/Middleware/EnsureTenantContext.php', 'handle', ['Organization', 'Workspace'], ['organizations', 'workspaces'], null, null, 'tests/Feature/Tenancy/AdversarialTenancyTest.php'],
        ['rbac', 'app/Http/Controllers/RoleController.php', 'roles', 'app/Http/Controllers/Domain/Auth/RoleController.php', 'store', ['Role', 'Permission'], ['roles', 'permissions'], 'resources/js/Pages/Roles/Index.tsx', 'roles.manage', 'tests/Feature/RBAC/RbacEnforcementHttpTest.php'],
        ['module-activation', 'app/Http/Controllers/ModuleController.php', 'modules', 'app/Http/Controllers/ModuleController.php', 'toggle', ['UserActiveModule'], ['user_active_modules'], 'resources/js/Pages/Modules/Index.tsx', 'modules.manage', 'tests/Feature/ModuleActivationTest.php'],
    ],
    'product-service' => [
        ['catalog', 'packages/workdo/ProductService/src/Http/Controllers/ProductServiceController.php', 'product-service', 'app/Http/Controllers/ProductServiceController.php', 'store', ['ProductServiceItem'], ['product_service_items'], 'resources/js/Pages/ProductService/Index.tsx', 'productservice.manage', 'tests/Feature/ProductService/ProductServiceInventoryTest.php'],
        ['categories-units-taxes', 'packages/workdo/ProductService/src/Routes/web.php', 'product-service', 'app/Http/Controllers/ProductServiceController.php', 'storeTax', ['ProductServiceCategory', 'ProductServiceUnit', 'ProductServiceTax'], ['product_service_categories', 'product_service_units', 'product_service_taxes'], 'resources/js/Pages/ProductService/Index.tsx', 'productservice.manage', 'tests/Feature/ProductService/ProductServiceInventoryTest.php'],
        ['warehouse-stock', 'app/Http/Controllers/WarehouseController.php', 'warehouses', 'app/Http/Controllers/WarehouseController.php', 'index', ['Warehouse', 'WarehouseStock'], ['warehouses', 'warehouse_stocks'], 'resources/js/Pages/Warehouses/Index.tsx', 'warehouses.view', 'tests/Feature/ProductService/ProductServiceInventoryTest.php'],
        ['stock-adjustments', 'packages/workdo/ProductService/src/Routes/web.php', 'adjust-stock', 'app/Http/Controllers/ProductServiceController.php', 'adjust', ['StockMovement'], ['stock_movements'], 'resources/js/Pages/ProductService/Index.tsx', 'inventory.adjust', 'tests/Feature/ProductService/ProductServiceInventoryTest.php'],
        ['stock-transfers', 'app/Http/Controllers/TransferController.php', 'transfers', 'app/Http/Controllers/TransferController.php', 'store', ['Transfer'], ['transfers'], 'resources/js/Pages/Transfers/Index.tsx', 'transfers.create', 'tests/Feature/SalesProcurement/SalesProcurementCoreTest.php'],
    ],
    'sales-procurement' => [
        ['purchase-invoices', 'app/Http/Controllers/PurchaseInvoiceController.php', 'purchase-invoices', 'app/Http/Controllers/PurchaseInvoiceController.php', 'post', ['PurchaseInvoice'], ['purchase_invoices', 'purchase_invoice_items'], 'resources/js/Pages/PurchaseInvoices/Index.tsx', 'purchase-invoices.manage', 'tests/Feature/SalesProcurement/SalesProcurementCoreTest.php'],
        ['purchase-returns', 'app/Http/Controllers/PurchaseReturnController.php', 'purchase-returns', 'app/Http/Controllers/PurchaseReturnController.php', 'complete', ['PurchaseReturn'], ['purchase_returns', 'purchase_return_items'], 'resources/js/Pages/PurchaseReturns/Index.tsx', 'purchase-returns.manage', 'tests/Feature/SalesProcurement/SalesProcurementCoreTest.php'],
        ['sales-proposals', 'app/Http/Controllers/SalesProposalController.php', 'sales-proposals', 'app/Http/Controllers/SalesProposalController.php', 'convertToInvoice', ['SalesProposal'], ['sales_proposals', 'sales_proposal_items'], 'resources/js/Pages/SalesProposals/Index.tsx', 'sales-proposals.manage', 'tests/Feature/SalesProcurement/SalesProcurementCoreTest.php'],
        ['sales-invoices', 'app/Http/Controllers/SalesInvoiceController.php', 'sales-invoices', 'app/Http/Controllers/SalesInvoiceController.php', 'post', ['SalesInvoice'], ['sales_invoices', 'sales_invoice_items'], 'resources/js/Pages/SalesInvoices/Index.tsx', 'sales-invoices.manage', 'tests/Feature/SalesProcurement/SalesProcurementCoreTest.php'],
        ['sales-returns', 'app/Http/Controllers/SalesReturnController.php', 'sales-returns', 'app/Http/Controllers/SalesReturnController.php', 'complete', ['SalesInvoiceReturn'], ['sales_invoice_returns', 'sales_invoice_return_items'], 'resources/js/Pages/SalesReturns/Index.tsx', 'sales-returns.manage', 'tests/Feature/SalesProcurement/SalesProcurementCoreTest.php'],
    ],
    'account' => [
        ['chart-of-accounts', 'packages/workdo/Account/src/Routes/web.php', 'accounting', 'app/Http/Controllers/AccountingController.php', 'storeAccount', ['LedgerAccount'], ['ledger_accounts'], 'resources/js/Pages/Accounting/Accounts.tsx', 'account.manage', 'tests/Feature/Modules/AccountingModuleTest.php'],
        ['journal-ledger', 'packages/workdo/Account/src/Routes/web.php', 'journals', 'app/Http/Controllers/AccountingController.php', 'storeJournal', ['JournalEntry', 'JournalLine'], ['journal_entries', 'journal_lines'], 'resources/js/Pages/Accounting/Journals.tsx', 'account.manage', 'tests/Feature/Modules/AccountingModuleTest.php'],
        ['bank-transfer-reconciliation', 'packages/workdo/Account/src/Routes/web.php', 'bank-reconciliations', 'app/Http/Controllers/AccountingController.php', 'reconcile', ['AccountBankTransfer'], ['bank_reconciliations', 'account_bank_transfers'], 'resources/js/Pages/Accounting/Reports.tsx', 'account.manage', 'tests/Feature/Modules/AccountingModuleTest.php'],
        ['financial-reports', 'packages/workdo/Account/src/Routes/web.php', 'reports', 'app/Http/Controllers/AccountingController.php', 'reports', ['LedgerAccount'], ['journal_lines'], 'resources/js/Pages/Accounting/Reports.tsx', 'account.view', 'tests/Feature/Modules/AccountingModuleTest.php'],
    ],
    'hrm' => [
        ['employees-structure', 'packages/workdo/Hrm/src/Routes/web.php', 'hrm', 'app/Http/Controllers/HrmController.php', 'storeEmployee', ['HrEmployee'], ['hr_employees', 'hr_departments', 'hr_designations'], 'resources/js/Pages/HRM/Index.tsx', 'hrm.manage', 'tests/Feature/Modules/HrmModuleTest.php'],
        ['attendance-leave', 'packages/workdo/Hrm/src/Routes/web.php', 'attendance', 'app/Http/Controllers/HrmController.php', 'attendance', ['HrAttendance', 'HrLeaveRequest'], ['hr_attendance', 'hr_leave_requests'], 'resources/js/Pages/HRM/Index.tsx', 'hrm.manage', 'tests/Feature/Modules/HrmModuleTest.php'],
        ['payroll-payslips', 'packages/workdo/Hrm/src/Routes/web.php', 'payslips', 'app/Http/Controllers/HrmController.php', 'generatePayslip', ['HrPayslip'], ['hr_payslips', 'hr_payslip_lines'], 'resources/js/Pages/HRM/Index.tsx', 'hrm.manage', 'tests/Feature/Modules/HrmModuleTest.php'],
        ['appraisals-documents', 'packages/workdo/Hrm/src/Routes/web.php', 'appraisals', 'app/Http/Controllers/HrmController.php', 'appraisal', ['HrEmployee'], ['hr_appraisals', 'hr_documents'], 'resources/js/Pages/HRM/Index.tsx', 'hrm.manage', 'tests/Feature/Modules/HrmModuleTest.php'],
    ],
    'crm' => [
        ['pipelines-stages', 'packages/workdo/Lead/src/Routes/web.php', 'pipeline', 'app/Http/Controllers/CrmController.php', 'storePipeline', ['CrmPipeline'], ['crm_pipelines', 'crm_stages'], 'resources/js/Pages/CRM/Index.tsx', 'crm.manage', 'tests/Feature/Modules/CrmModuleTest.php'],
        ['leads-activities', 'packages/workdo/Lead/src/Routes/web.php', 'lead', 'app/Http/Controllers/CrmController.php', 'storeLead', ['CrmLead'], ['crm_leads', 'crm_activities', 'crm_notes'], 'resources/js/Pages/CRM/Index.tsx', 'crm.manage', 'tests/Feature/Modules/CrmModuleTest.php'],
        ['deals-conversion', 'packages/workdo/Lead/src/Routes/web.php', 'deal', 'app/Http/Controllers/CrmController.php', 'convertLead', ['CrmDeal'], ['crm_deals'], 'resources/js/Pages/CRM/Index.tsx', 'crm.manage', 'tests/Feature/Modules/CrmModuleTest.php'],
    ],
    'taskly' => [
        ['projects-members', 'packages/workdo/Taskly/src/Routes/web.php', 'projects', 'app/Http/Controllers/TasklyController.php', 'storeProject', ['TasklyProject'], ['taskly_projects', 'taskly_project_members'], 'resources/js/Pages/Taskly/Index.tsx', 'taskly.manage', 'tests/Feature/Modules/TasklyModuleTest.php'],
        ['tasks-kanban', 'packages/workdo/Taskly/src/Routes/web.php', 'tasks', 'app/Http/Controllers/TasklyController.php', 'storeTask', ['TasklyTask'], ['taskly_tasks', 'taskly_stages'], 'resources/js/Pages/Taskly/Index.tsx', 'taskly.manage', 'tests/Feature/Modules/TasklyModuleTest.php'],
        ['milestones-timesheets', 'packages/workdo/Taskly/src/Routes/web.php', 'milestones', 'app/Http/Controllers/TasklyController.php', 'milestone', ['TasklyTimesheet'], ['taskly_milestones', 'taskly_timesheets'], 'resources/js/Pages/Taskly/Index.tsx', 'taskly.manage', 'tests/Feature/Modules/TasklyModuleTest.php'],
        ['issues-comments-cost', 'packages/workdo/Taskly/src/Routes/web.php', 'issues', 'app/Http/Controllers/TasklyController.php', 'issue', ['TasklyTask'], ['taskly_issues', 'taskly_comments'], 'resources/js/Pages/Taskly/Index.tsx', 'taskly.manage', 'tests/Feature/Modules/TasklyModuleTest.php'],
    ],
    'pos' => [
        ['register-sessions', 'packages/workdo/Pos/src/Routes/web.php', 'pos', 'app/Http/Controllers/PosController.php', 'openSession', ['PosRegister', 'PosSession'], ['pos_registers', 'pos_sessions'], 'resources/js/Pages/POS/Index.tsx', 'pos.manage', 'tests/Feature/Modules/PosModuleTest.php'],
        ['checkout-payment-stock', 'packages/workdo/Pos/src/Routes/web.php', 'checkout', 'app/Http/Controllers/PosController.php', 'checkout', ['PosOrder'], ['pos_orders', 'pos_order_items'], 'resources/js/Pages/POS/Index.tsx', 'pos.sell', 'tests/Feature/Modules/PosModuleTest.php'],
        ['returns-reconciliation', 'packages/workdo/Pos/src/Routes/web.php', 'return', 'app/Http/Controllers/PosController.php', 'refund', ['PosOrder'], ['pos_returns', 'pos_return_items'], 'resources/js/Pages/POS/Index.tsx', 'pos.refund', 'tests/Feature/Modules/PosModuleTest.php'],
    ],
    'platform-services' => [
        ['landing-publishing', 'packages/workdo/LandingPage/src/Routes/web.php', 'landing', 'app/Http/Controllers/LandingPageController.php', 'publish', ['LandingPage', 'LandingSite'], ['landing_pages', 'landing_sections'], 'resources/js/Pages/Landing/Manage.tsx', 'landingpage.manage', 'tests/Feature/Modules/LandingPageModuleTest.php'],
        ['saas-plans-trials', 'app/Http/Controllers/PlanController.php', 'plans', 'app/Http/Controllers/Domain/SaaS/PlanController.php', 'startTrial', ['Plan', 'Subscription'], ['plans', 'subscriptions'], 'resources/js/Pages/Plans/Index.tsx', 'plans.manage', 'tests/Feature/SaaS/CompleteSaaSEngineTest.php'],
        ['saas-coupons-orders', 'app/Http/Controllers/CouponController.php', 'coupons', 'app/Http/Controllers/Domain/SaaS/CouponController.php', 'store', ['Coupon', 'Order'], ['coupons', 'orders'], 'resources/js/Pages/Coupons/Index.tsx', 'coupons.manage', 'tests/Feature/SaaS/CompleteSaaSEngineTest.php'],
        ['bank-transfer-review', 'app/Http/Controllers/BankTransferPaymentController.php', 'bank-transfer', 'app/Http/Controllers/BankTransferPaymentController.php', 'approve', ['BankTransferPayment'], ['bank_transfer_payments'], 'resources/js/Pages/BankTransfer/Index.tsx', 'bank-transfer.manage', 'tests/Feature/SaaS/CompleteSaaSEngineTest.php'],
        ['helpdesk', 'app/Http/Controllers/HelpdeskTicketController.php', 'helpdesk-tickets', 'app/Http/Controllers/HelpdeskTicketController.php', 'store', ['HelpdeskTicket', 'HelpdeskReply'], ['helpdesk_tickets', 'helpdesk_replies'], 'resources/js/Pages/Helpdesk/Tickets/Index.tsx', 'helpdesk.manage', 'tests/Feature/CommunicationsAndHelpdesk/CommunicationsHelpdeskMediaTest.php'],
        ['media-library', 'app/Http/Controllers/MediaController.php', 'media', 'app/Http/Controllers/MediaController.php', 'batchStore', ['Media', 'MediaDirectory'], ['media', 'media_directories'], 'resources/js/Pages/Media/Index.tsx', null, 'tests/Feature/CommunicationsAndHelpdesk/CommunicationsHelpdeskMediaTest.php'],
        ['messenger', 'app/Http/Controllers/MessengerController.php', 'chats', 'app/Http/Controllers/MessengerController.php', 'send', ['ChMessage'], ['ch_messages'], 'resources/js/Pages/Messenger/Index.tsx', null, 'tests/Feature/CommunicationsAndHelpdesk/CommunicationsHelpdeskMediaTest.php'],
        ['assistant', 'app/Http/Controllers/AIAgentChatController.php', 'ai-agent', 'app/Services/AssistantConversationService.php', 'send', ['AssistantSession', 'AssistantMessage'], ['ai_agent_chat_sessions', 'ai_agent_chat_messages'], 'resources/js/Pages/AIAssistant/Index.tsx', null, 'tests/Feature/CommunicationsAndHelpdesk/CommunicationsHelpdeskMediaTest.php'],
        ['hierarchical-settings', 'app/Http/Controllers/SettingController.php', 'settings', 'app/Services/HierarchicalSettingService.php', 'put', ['Setting'], ['settings'], 'resources/js/Pages/Settings/Index.tsx', null, 'tests/Feature/Settings/SettingsLocalizationTemplatesTest.php'],
        ['localization', 'app/Http/Controllers/TranslationController.php', 'languages', 'app/Http/Controllers/LanguageController.php', 'saveLanguageData', ['Language'], ['languages'], 'resources/js/Pages/Languages/Index.tsx', null, 'tests/Feature/Settings/SettingsLocalizationTemplatesTest.php'],
        ['email-notifications', 'app/Http/Controllers/EmailTemplateController.php', 'email-templates', 'app/Services/LocalizedTemplateRenderer.php', 'email', ['EmailTemplate', 'NotificationTemplate'], ['email_templates', 'notifications'], 'resources/js/Pages/Settings/EmailTemplates/Index.tsx', null, 'tests/Feature/Settings/SettingsLocalizationTemplatesTest.php'],
        ['api-v1', 'app/Http/Controllers/Api/AuthApiController.php', 'api', 'app/Http/Controllers/Api/V1/AuthController.php', 'login', ['User'], ['personal_access_tokens'], null, null, 'tests/Feature/Api/ApiV1SuiteTest.php'],
        ['webhooks', 'app/Events/CreateMetaWebhook.php', 'webhooks', 'app/Http/Controllers/WebhookController.php', 'store', ['Webhook'], ['webhooks', 'webhook_deliveries'], 'resources/js/Pages/Webhooks/Index.tsx', 'webhooks.manage', 'tests/Feature/Webhooks/WebhookRuntimeTest.php'],
        ['module-package-runtime', 'app/Http/Controllers/ModuleController.php', 'modules/install', 'app/Domain/Modules/SecureModuleInstaller.php', 'install', ['UserActiveModule'], ['user_active_modules'], 'resources/js/Pages/Modules/Index.tsx', 'modules.manage', 'tests/Feature/Modules/SecureModuleInstallerTest.php'],
        ['addon-runtime', 'app/Models/AddOn.php', 'modules', 'app/Domain/Addons/AddonManager.php', 'enable', ['Addon'], ['addons', 'workspace_addons'], null, null, 'tests/Feature/Addons/AddonRuntimeTest.php'],
        ['licensing', 'app/Http/Controllers/InstallerController.php', 'licensing', 'app/Domain/Licensing/Services/LicenseActivationService.php', 'activate', ['License'], ['licenses', 'license_activations'], null, null, 'tests/Feature/LicensingLifecycleTest.php'],
        ['installer', 'app/Http/Controllers/InstallerController.php', 'install', 'app/Http/Controllers/InstallController.php', 'setup', ['User'], ['users'], 'resources/js/Pages/Install/Index.tsx', null, 'tests/Feature/InstallerAndLicensing/InstallerLicensingSuiteTest.php'],
        ['updater', 'app/Http/Controllers/UpdaterController.php', 'update', 'app/Domain/Updates/UpdateManager.php', 'install', ['UpdateHistory'], ['update_histories'], 'resources/js/Pages/Update/Index.tsx', null, 'tests/Feature/InstallerAndLicensing/SecureUpdaterTest.php'],
        ['audit-request-ids', 'app/Models/LoginHistory.php', 'dashboard', 'packages/hiddenleaf/kernel/src/Services/AuditLogger.php', 'logCritical', ['AuditLog'], ['audit_logs'], null, null, 'tests/Feature/Security/AuditLedgerTest.php'],
    ],
];

$records = [];
foreach ($domains as $classification => $capabilities) {
    foreach ($capabilities as [$id, $referenceFile, $route, $hiddenleafFile, $method, $models, $tables, $screen, $permission, $test]) {
        $records[] = [
            'id' => $classification.'.'.$id,
            'classification' => $classification,
            'reference_file' => $referenceFile,
            'reference_route' => $route,
            'reference_controller' => basename($referenceFile, '.php'),
            'reference_method' => $method,
            'reference_models' => $models,
            'reference_tables' => $tables,
            'reference_screen' => $screen,
            'reference_permission' => $permission,
            'hiddenleaf_file' => $hiddenleafFile,
            'hiddenleaf_route' => $route,
            'hiddenleaf_controller' => basename($hiddenleafFile, '.php'),
            'hiddenleaf_method' => $method,
            'hiddenleaf_models' => $models,
            'hiddenleaf_tables' => $tables,
            'hiddenleaf_screen' => $screen,
            'hiddenleaf_permission' => $permission,
            'hiddenleaf_tests' => [$test],
        ];
    }
}

return $records;
