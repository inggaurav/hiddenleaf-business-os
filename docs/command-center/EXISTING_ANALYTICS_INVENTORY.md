# Existing Analytics & Telemetry Inventory

This document maps all authoritative metrics, signals, and telemetry services across the HiddenLeaf Business OS modules. The Mr. Fox Executive Command Center aggregates and explains these canonical sources rather than duplicating or inventing new storage mechanisms.

| Metric / Signal Domain | Canonical Database Model / Service Source | Existing Query / Endpoint | Command Center Action |
|---|---|---|---|
| **Cash & Liquidity** | `BankAccount`, `BankTransferPayment`, `CustomerPayment`, `VendorPayment` | `AccountingController`, `BankTransferPaymentController` | **Reuse & Extend**: Compute real-time cash position, 30-day cash velocity, and liquidity reserve score. |
| **Receivables & Invoicing** | `SalesInvoice`, `CreditNote` | `SalesInvoiceController`, `HomeController::Dashboard` | **Reuse & Extend**: Calculate overdue receivables, 30/60/90+ day aging buckets, and customer default risk. |
| **Payables & Expenses** | `PurchaseInvoice`, `DebitNote` | `PurchaseInvoiceController`, `HomeController::Dashboard` | **Reuse & Extend**: Track upcoming and overdue vendor bills, expense spikes, and net operational margin. |
| **Sales Pipeline** | `CrmLead`, `CrmDeal`, `CrmPipeline`, `CrmStage` | `CrmController`, `CrmSearchLeadsTool` | **Reuse & Extend**: Stage distribution, win/loss conversion rates, stalled deals, and dormant warm leads. |
| **Inventory & Stock** | `ProductServiceItem`, `Warehouse`, `WarehouseStock`, `ProductStockMovement` | `ProductServiceController`, `WarehouseController` | **Reuse & Extend**: Low stock alerts, out-of-stock items, turnover velocity, and warehouse rebalancing signals. |
| **Projects & Tasks** | `TasklyProject`, `TasklyTask`, `TasklyMilestone` | `TasklyController`, `HomeController::Dashboard` | **Reuse & Extend**: Overdue tasks, blocked milestones, unassigned high-priority work, project completion rates. |
| **Human Resources** | `HrEmployee`, `HrAttendance`, `HrLeave` | `HrmController`, `HomeController::Dashboard` | **Reuse & Extend**: Attendance rates, pending leave requests, department staffing levels (RBAC gated). |
| **Customer Communications** | `CommunicationConversation`, `CommunicationMessage` | `CommunicationSyncService`, `UnifiedInbox` | **Reuse & Extend**: Unread counts, urgent priority scores (>75), wait-time SLA violations (>24h), failed deliveries. |
| **Helpdesk Support** | `HelpdeskTicket`, `HelpdeskReply` | `HelpdeskTicketController` | **Reuse & Extend**: Open tickets, critical escalated issues, average resolution time trends. |
| **Marketing & Brand** | `MrFoxBrandProfile`, `MrFoxSocialInteraction` | `BrandProfileController`, `SocialInbox` | **Reuse & Extend**: Inbound social sentiment, brand tone alignment, pending content review approvals. |
| **Automations Engine** | `AutomationRule`, `AutomationRun`, `AutomationRunStep` | `AutomationEngine`, `AutomationRuleController` | **Reuse & Extend**: Rule health, run success rates, repeated failure cascades, pending approval steps. |
| **Mr. Fox Missions** | `MrFoxMission`, `MrFoxMissionStep`, `MrFoxActionProposal` | `MissionExecutor`, `ActionApprovalService` | **Reuse & Extend**: Active missions, waiting approvals, stalled goals, token budget consumption. |
