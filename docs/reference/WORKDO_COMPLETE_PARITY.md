# WorkDo ERP Complete Parity Report
**Date**: 2026-08-13
**System**: WorkDo ERP OS
**Scope**: Forensic Capability Inventory

This report classifies every discovered component within the WorkDo ERP SaaS main repository. The system is structurally a modular Laravel application leveraging an internal package/module architecture under `packages/workdo`. 

## Component Classifications

Each discovered capability is assigned one of the following labels:
- **WORKDO_CORE**: Essential platform capabilities (authentication, plans, media, system config, support).
- **BUNDLED_CORE_MODULE**: Major functional domains included by default (HRM, Account, Lead, Taskly, etc.).
- **DEFERRED_ADDON**: Extendable functionality (e.g., Payment integrations).
- **REFERENCE_INFRASTRUCTURE**: Dev/Ops architecture components.
- **DEPRECATED**: Features that have been superceded or removed over time.

---

### 1. SaaS Platform Core (`WORKDO_CORE`)
Handles multi-tenant architecture, workspace segregation, permissions, and the add-on ecosystem.
- **Sub-capabilities**: Authentication & Identity, Plans & Subscriptions, Multi-Workspace Setup, Roles & Permissions, Module Loader.
- **Key Files**: `User.php`, `Plan.php`, `Order.php`, `UserActiveModule.php`, `RoleController.php`, `AuthApiController.php`.

### 2. Helpdesk & Support (`WORKDO_CORE`)
Built-in ticketing system for user support.
- **Sub-capabilities**: Ticketing System, Replies & Attachments, Categories.
- **Key Files**: `HelpdeskTicket.php`, `HelpdeskReply.php`, `HelpdeskCategory.php`.

### 3. Communications Engine (`WORKDO_CORE`)
Internal messaging, external email templates, and AI integration.
- **Sub-capabilities**: Email Templates, Notifications, Chat/Messenger (Chatify-based), AI Agent Chat.
- **Key Files**: `EmailTemplate.php`, `Notification.php`, `ChMessage.php`, `AIAgentChatSession.php`.

### 4. System Configuration & Media (`WORKDO_CORE`)
Platform-wide settings, storage configurations, and localization.
- **Sub-capabilities**: Global Settings, Dynamic Storage, Media Directory, Localization.
- **Key Files**: `Setting.php`, `MediaDirectory.php`, `DynamicStorageService.php`.

### 5. Sales & Procurement Core Engine (`WORKDO_CORE`)
Base models providing standard transaction architectures used by multiple modules.
- **Sub-capabilities**: Sales Proposals, Invoices, Purchase Orders, Returns, Warehouses, Transfers.
- **Key Files**: `SalesInvoice.php`, `PurchaseInvoice.php`, `Warehouse.php`, `Transfer.php`.

---

### 6. Accounting Module (`BUNDLED_CORE_MODULE`)
Domain: `packages/workdo/Account`
Comprehensive financial record-keeping module.
- **Sub-capabilities**: Chart of Accounts, Banking & Transactions, Journals, Vendor & Customer Payments, Credit/Debit Notes.
- **Key Files**: `ChartOfAccount.php`, `JournalEntry.php`, `BankTransaction.php`.

### 7. HR & Payroll Module (`BUNDLED_CORE_MODULE`)
Domain: `packages/workdo/Hrm`
Human Resource Management lifecycle.
- **Sub-capabilities**: Employee Management, Attendance & Leaves, Payroll Processing, Policies & Documents, Shifts & Awards.
- **Key Files**: `Employee.php`, `Payroll.php`, `LeaveApplication.php`.

### 8. CRM & Leads Module (`BUNDLED_CORE_MODULE`)
Domain: `packages/workdo/Lead`
Sales funnel management.
- **Sub-capabilities**: Pipelines & Stages, Lead Tracking, Deal Management, Activity Logging.
- **Key Files**: `Lead.php`, `Deal.php`, `Pipeline.php`.

### 9. Project Management Module (`BUNDLED_CORE_MODULE`)
Domain: `packages/workdo/Taskly`
Work execution and tracking.
- **Sub-capabilities**: Projects & Milestones, Tasks & Subtasks, Bug Tracking, Kanban & Gantt.
- **Key Files**: `Project.php`, `ProjectTask.php`, `ProjectBug.php`.

### 10. Point of Sale Module (`BUNDLED_CORE_MODULE`)
Domain: `packages/workdo/Pos`
In-store / Direct billing operations.
- **Sub-capabilities**: POS Registers, Billing Counters, Cart & Discounts, Payments & Returns.
- **Key Files**: `Pos.php`, `PosPayment.php`, `PosReturn.php`.

### 11. Product & Inventory Module (`BUNDLED_CORE_MODULE`)
Domain: `packages/workdo/ProductService`
Catalog management and stock tracking.
- **Sub-capabilities**: Products & Services, Categories & Units, Warehouse Stock Tracking.
- **Key Files**: `ProductServiceItem.php`, `WarehouseStock.php`.

### 12. Frontend CMS (`BUNDLED_CORE_MODULE`)
Domain: `packages/workdo/LandingPage`
Public-facing pages and integrations.
- **Sub-capabilities**: Custom Pages, Marketplace Configuration, Newsletter Subscribers.
- **Key Files**: `CustomPage.php`, `MarketplaceSetting.php`.

---

### 13. Payment Gateways (`DEFERRED_ADDON`)
Third-party integrations intended for marketplace addition.
- **Sub-capabilities**: Paypal Integration, Stripe Integration.
- **Key Files**: `packages/workdo/Paypal`, `packages/workdo/Stripe`.

---

### 14. Development & Deployment Infrastructure (`REFERENCE_INFRASTRUCTURE`)
Tooling for installation and maintenance.
- **Sub-capabilities**: Module Installer, Translation Extraction, Console Crud Generators, Server Requirements Check.
- **Key Files**: `MakeCrudlyCommand.php`, `InstallCommand.php`, `server-requirements.php`, `extract-translations.php`.

---

### 15. Obsolete / Superceded Features (`DEPRECATED`)
Historical logic that has been pruned from the operational system.
- **Sub-capabilities**: Legacy Trial Data (from migrations).
- **Key Files**: `database/migrations/2026_04_29_102148_drop_trial_expire_date_from_users_table.php`.
