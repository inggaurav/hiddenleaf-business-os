# WorkDo Add-ons Deferred to Stage 2

The following capabilities were discovered in the reference codebase and are explicitly classified as `WORKDO_ADDON`. They are deferred to Stage 2 to ensure Stage 1 focuses strictly on building a production-stable WorkDo SaaS CORE baseline.

## Deferred Add-on Catalog

| Add-on / Module Name | Reference Location | Target Stage | Description & Dependency Notes |
| :--- | :--- | :--- | :--- |
| **Account Module** | `packages/workdo/Account` | Stage 2 | Double-entry accounting, customer/vendor management, chart of accounts, bank transfers, financial reporting. |
| **HRM Module** | `packages/workdo/Hrm` | Stage 2 | Human Resource Management, payroll, attendance, leave management, employee directory, performance evaluation. |
| **Lead / CRM Module** | `packages/workdo/Lead` | Stage 2 | Lead tracking, pipelines, deal management, estimation, lead conversion workflows. |
| **POS (Point of Sale) Module** | `packages/workdo/Pos` | Stage 2 | Point of sale interface, barcode scanning, register management, thermal receipt printing. |
| **Taskly Module** | `packages/workdo/Taskly` | Stage 2 | Project management, Kanban task boards, milestone tracking, timesheets, project budget tracking. |
| **LandingPage Module** | `packages/workdo/LandingPage` | Stage 2 | Custom marketing landing page builder, hero sections, feature grids, testimonial manager. |
| **Helpdesk Ticket System** | `HelpdeskTicketController.php`, `HelpdeskCategoryController.php` | Stage 2 | Customer support ticketing system, ticket replies, SLA management, custom ticket categories. |
| **Internal Messenger** | `MessengerController.php`, `app/Models/ChMessage.php` | Stage 2 | Real-time chat messenger, media sharing, pinned messages, favorites, online status indicators. |
| **AI Agent / Mr Fox / AI Council** | `AIAgentChatController.php`, `app/Models/AIAgentChatMessage.php` | Stage 2+ | AI assistant integration, conversational UI, automated prompt responses, context grounding. |
| **Sales & Purchase Invoices / Proposals** | `SalesProposalController.php`, `SalesInvoiceController.php` | Stage 2 | Advanced invoicing, quote generation, purchase orders, inventory updates (part of Account/ERP). |
| **External Payment Gateways Catalog** | Reference Payment Extensions | Stage 2 | Additional payment gateway integrations (Razorpay, Flutterwave, Paystack, Mollie, MercadoPago, etc.). |
| **Social & Third-Party Integrations** | Meta / WhatsApp / Slack / Shopify | Stage 2 | WhatsApp notification bot, Slack workspace alerts, Shopify catalog sync, Facebook/Meta webhook sync. |

---

## Architectural Preparation in Stage 1

While these add-ons will be implemented in Stage 2:
1. Stage 1 includes the complete **`AddonRegistry`**, **`AddonManager`**, and **`AddonContract`** in the kernel.
2. The dynamic database migration runner, permissions dynamic loader, and dynamic menu contribution pipeline are fully active in Stage 1.
3. Adding any of the above packages in Stage 2 will require ZERO core architectural changes.
