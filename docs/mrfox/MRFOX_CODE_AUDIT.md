# Mr. Fox Intelligence Layer — Code Inventory & Architecture Audit

**Branch**: `develop/hiddenleaf-v2`  
**Parity Baseline**: `workdo-parity-v1` (`dffd221`)  
**Audit Date**: 2026-08-15  

---

## 1. Domain Architecture (`app/Domain/MrFox/`)

| File | Purpose / Responsibility |
|---|---|
| `app/Domain/MrFox/RiskLevel.php` | Enum (`READ`, `LOW`, `MEDIUM`, `HIGH`, `CRITICAL`) defining operational risk tiers and human approval thresholds. |
| `app/Domain/MrFox/Contracts/AiProviderContract.php` | Interface for pluggable LLM backends (`name()`, `isAvailable()`, `chat(AiRequest)`). |
| `app/Domain/MrFox/Contracts/MrFoxToolContract.php` | Interface for ERP tools (`name()`, `description()`, `inputSchema()`, `requiredPermission()`, `requiredModule()`, `riskLevel()`, `execute()`). |
| `app/Domain/MrFox/DTO/AiRequest.php` | Immutable DTO carrying messages, system prompts, tool definitions, and workspace context. |
| `app/Domain/MrFox/DTO/AiResponse.php` | Immutable DTO carrying text content, tool calls, token usage, provider, and model metadata. |
| `app/Domain/MrFox/DTO/ToolContext.php` | Context DTO holding authenticated user, workspace, organization, and conversation ID. |
| `app/Domain/MrFox/DTO/ToolResult.php` | Standardized result DTO carrying data payload, human summary, evidence records, and proposal status. |
| `app/Domain/MrFox/Agent/MrFoxAgent.php` | Central orchestrator managing business context compilation, tool resolution, validation, approval routing, audit logging, and token tracking. |
| `app/Domain/MrFox/Approvals/ActionApprovalService.php` | Lifecycle engine for high-risk action proposals (`createProposal`, `approveAndExecute`, `reject`). |
| `app/Domain/MrFox/Context/BusinessContextService.php` | Tenant context compiler assembling user profile, organization, workspace, enabled modules, and active page context. |
| `app/Domain/MrFox/Insights/BusinessInsightService.php` | Deterministic signals engine computing receivables, low stock, overdue tasks, pending leaves, and active pipeline backed by ERP evidence. |
| `app/Domain/MrFox/Observability/MrFoxAuditService.php` | Audit logger persisting sanitized, secret-redacted execution records with trace IDs and execution timing. |
| `app/Domain/MrFox/Observability/MrFoxUsageService.php` | Usage ledger tracking prompt/completion tokens per workspace, user, provider, and capability. |
| `app/Domain/MrFox/Providers/FakeAiProvider.php` | Deterministic mock provider for CI/test environments and offline validation. |
| `app/Domain/MrFox/Providers/OpenAiProvider.php` | OpenAI API provider supporting function calling, timeouts, backoff, and error normalization. |
| `app/Domain/MrFox/Providers/GeminiProvider.php` | Google Gemini 1.5 Pro provider supporting structured generation and error handling. |
| `app/Domain/MrFox/Providers/ProviderRouter.php` | Dynamic settings resolver matching workspace configurations to providers. |
| `app/Domain/MrFox/Tools/MrFoxToolRegistry.php` | Central catalog of all registered ERP tools with permission and module availability filtering. |

---

## 2. ERP Business Tools (`app/Domain/MrFox/Tools/`)

| Tool Identifier | Class File | Module | Risk | Required Permission |
|---|---|---|---|---|
| `business.dashboard.summary` | `BusinessDashboardSummaryTool.php` | Core | READ | None (Workspace Member) |
| `business.alerts` | `BusinessAlertsTool.php` | Core | READ | None (Workspace Member) |
| `crm.search.leads` | `CrmSearchLeadsTool.php` | `crm` | READ | `crm.manage` |
| `crm.get.lead` | `CrmGetLeadTool.php` | `crm` | READ | `crm.manage` |
| `crm.pipeline.summary` | `CrmPipelineSummaryTool.php` | `crm` | READ | `crm.manage` |
| `crm.create.lead` | `CrmCreateLeadTool.php` | `crm` | LOW | `crm.manage` |
| `crm.add.note` | `CrmAddNoteTool.php` | `crm` | LOW | `crm.manage` |
| `sales.invoice.search` | `SalesInvoiceSearchTool.php` | `account` | READ | `sales.manage` |
| `sales.outstanding.summary` | `SalesOutstandingSummaryTool.php` | `account` | READ | `sales.manage` |
| `purchase.bill.search` | `PurchaseBillSearchTool.php` | `account` | READ | `purchases.manage` |
| `purchase.payables.summary` | `PurchasePayablesSummaryTool.php` | `account` | READ | `purchases.manage` |
| `accounting.pnl` | `AccountingPnlTool.php` | `account` | READ | `account.reports` |
| `accounting.cash_position` | `AccountingCashPositionTool.php` | `account` | READ | `account.reports` |
| `inventory.stock.summary` | `InventoryStockSummaryTool.php` | `productservice` | READ | `inventory.manage` |
| `inventory.low_stock` | `InventoryLowStockTool.php` | `productservice` | READ | `inventory.manage` |
| `taskly.search.projects` | `TasklySearchProjectsTool.php` | `taskly` | READ | `projects.manage` |
| `taskly.overdue.tasks` | `TasklyOverdueTasksTool.php` | `taskly` | READ | `projects.manage` |
| `taskly.create.task` | `TasklyCreateTaskTool.php` | `taskly` | LOW | `projects.manage` |
| `hr.employee.summary` | `HrEmployeeSummaryTool.php` | `hrm` | READ | `hrm.manage` |
| `hr.attendance.summary` | `HrAttendanceSummaryTool.php` | `hrm` | READ | `hrm.manage` |
| `hr.leave.pending` | `HrPendingLeaveTool.php` | `hrm` | READ | `hrm.manage` |

---

## 3. Database Models, Migrations & Persistence

| File | Purpose |
|---|---|
| `database/migrations/2026_08_15_100000_create_mrfox_intelligence_tables.php` | Schema for proposals, audit logs, and token usage records. |
| `database/migrations/2026_08_15_110000_create_mrfox_conversations_and_hardening_tables.php` | Schema for persistent conversations, messages, and proposal payload hashes. |
| `app/Models/MrFoxActionProposal.php` | Eloquent model for human-in-the-loop action proposals. |
| `app/Models/MrFoxAuditLog.php` | Eloquent model for immutable tool execution audit trails. |
| `app/Models/MrFoxUsageRecord.php` | Eloquent model for token usage tracking. |
| `app/Models/MrFoxConversation.php` | Eloquent model for persistent multi-tenant chat conversations. |
| `app/Models/MrFoxMessage.php` | Eloquent model for chat messages with tool calls, results, and evidence. |

---

## 4. HTTP Controllers, Service Providers & Routes

| File | Purpose |
|---|---|
| `app/Http/Controllers/MrFox/MrFoxChatController.php` | REST API endpoints for chat, conversations, insights, action approval, and rejection. |
| `app/Providers/MrFoxServiceProvider.php` | Singleton registrations for Mr. Fox registry, router, context, approvals, audit, and agent. |
| `routes/web.php` | Registered `/api/v1/mr-fox/*` route group protected with `auth:sanctum` and tenant resolution. |

---

## 5. Frontend Components

| File | Purpose |
|---|---|
| `resources/js/Components/MrFox/MrFoxPanel.tsx` | Slide-over drawer with real-time streaming/chat, tool execution pills, evidence badges, and action proposal cards. |
| `resources/js/Pages/Dashboard.tsx` | Main dashboard view hosting the **Mr. Fox Executive Intelligence & Signals** widget. |

---

## 6. Test Suites (`tests/Feature/MrFox/`)

| Test Suite | Purpose |
|---|---|
| `tests/Feature/MrFox/MrFoxArchitectureTest.php` | Core agent execution, chat endpoint, and insight verification. |
| `tests/Feature/MrFox/MrFoxTenantIsolationTest.php` | Cross-tenant data isolation and tool scoping. |
| `tests/Feature/MrFox/MrFoxActionApprovalTest.php` | Action proposal lifecycle, human approval, and rejection. |
| `tests/Feature/MrFox/MrFoxToolRegistryTest.php` | Tool discovery, duplicate rejection, and module filtering. |
| `tests/Feature/MrFox/MrFoxInputValidationTest.php` | Server-side validation of tool inputs. |
| `tests/Feature/MrFox/MrFoxRbacNegativeTest.php` | Negative authorization checks on tools. |
| `tests/Feature/MrFox/MrFoxFullTenantIsolationTest.php` | Comprehensive isolation tests for all 21 tools. |
| `tests/Feature/MrFox/MrFoxApprovalSecurityTest.php` | Immutability, expiry, revocation, and double execution guards. |
| `tests/Feature/MrFox/MrFoxAuditRedactionTest.php` | Secret and credential scrubbing in audit logs. |
| `tests/Feature/MrFox/MrFoxUsageQuotaTest.php` | Token ledger integrity and monthly quota enforcement. |
| `tests/Feature/MrFox/MrFoxProviderFailureTest.php` | Provider timeouts, 429 rate limits, and error normalization. |
| `tests/Feature/MrFox/MrFoxPromptInjectionBoundaryTest.php` | Prompt injection resistance on tool invocation. |
| `tests/Feature/MrFox/MrFoxFinancialParityTest.php` | Parity checks of Mr. Fox financial figures against canonical domain services. |
| `tests/Feature/MrFox/MrFoxConversationIdorTest.php` | Multi-tenant isolation for conversations and messages. |
| `tests/Feature/MrFox/MrFoxPostgresApprovalConcurrencyTest.php` | PostgreSQL row-lock concurrency proof for approval execution. |
