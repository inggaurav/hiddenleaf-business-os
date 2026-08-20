# HIDDENLEAF BUSINESS OS — CRM & MR FOX ARCHITECTURE AUDIT

**Repository**: `inggaurav/hiddenleaf-business-os`  
**Baseline Branch**: `release/v1.0.0-rc1`  
**Implementation Branch**: `release/hiddenleaf-mrfox-mvp`  
**Verified Baseline HEAD**: `4ed72f6961eaf6661b13aafa89f39633075e0b60`  
**Audit Timestamp**: 2026-08-20  

---

## 1. DOMAIN ARCHITECTURE INVENTORY

### Authentication & Multi-Tenancy
- **Authentication**: Laravel Sanctum + Session Auth (`App\Http\Controllers\Auth\AuthController`). Full 2FA support and rate-limited login endpoints.
- **Tenant Context**: Enforced via `App\Http\Middleware\EnsureTenantContext` and `HiddenLeaf\Kernel\Contexts\TenantContext`. Every single query requires `organization_id` and `workspace_id`.
- **RBAC**: `HiddenLeaf\Kernel\Registries\PermissionRegistry` and middleware `EnsureAccountPermission`, `EnsurePosPermission`, `EnsureProductServicePermission`.
- **Tenant Data Isolation**: Strictly enforced in Eloquent models via `scopeForWorkspace($orgId, $workspaceId)`.

### CRM Domain
- **Lead Management**: `App\Models\CrmLead`, `App\Http\Controllers\CrmController::storeLead`, `moveLead`, `convertLead`.
- **Pipeline & Deal Management**: `App\Models\CrmPipeline`, `App\Models\CrmStage`, `App\Models\CrmDeal`. Dynamic stage updates with probability tracking and outcome classification (`won`, `lost`).
- **Customer Conversion**: `CrmController::convertLead` converts qualified leads directly to `App\Models\AccountCustomer` and creates `CrmDeal` within an atomic DB transaction with audit logging (`lead.converted`).
- **Notes & Webforms**: `App\Models\CrmNote`, `App\Models\CrmWebform` with honeypot spam protection (`hp_fax`) and tokenized public submissions (`publicWebformSubmit`).
- **CRM Dashboard & Analytics**: `App\Domain\CRM\CrmDashboardService` providing truthful funnel metrics, pipeline values, conversion rates, and stage distribution.

### Sales & Financial Domains
- **Proposals & Invoices**: `App\Http\Controllers\SalesProposalController`, `App\Http\Controllers\SalesInvoiceController`, `App\Models\SalesInvoice`, `App\Models\SalesProposal`.
- **Accounting & General Ledger**: `App\Domain\Accounting\CommercialAccountingService`, `FinancialBalanceService`, `LedgerService`. Double-entry book-keeping with strict concurrency safety.

### Operations, Communications & Command Center
- **Communications**: `App\Http\Controllers\Communications\UnifiedInboxController`, `App\Services\InternalMessengerAdapter`.
- **Automation & Missions**: `App\Http\Controllers\Automation\AutomationRuleController`, `App\Domain\Automation\Actions\ActionRegistry`, `TriggerRegistry`.
- **Executive Command Center**: `App\Http\Controllers\CommandCenter\CommandCenterController`, `BusinessHealthService`, `SignalDetector`, `AnomalyDetectionEngine`, `ExecutiveBriefingService`.

---

## 2. MR FOX ENGINE ARCHITECTURE

Mr Fox is built directly into `app/Domain/MrFox/` as an executive operating agent sitting above all deterministic business domains.

```
                    HIDDENLEAF BUSINESS OS
                              │
                    ┌─────────┴─────────┐
                    │                   │
               BUSINESS UI          MR FOX
                    │                   │
                    └─────────┬─────────┘
                              │
                    BUSINESS DOMAIN LAYER
                              │
          ┌───────────────────┼────────────────────┐
          │                   │                    │
         CRM              OPERATIONS            FINANCE
          │                   │                    │
          └───────────────────┼────────────────────┘
                              │
                         MR FOX TOOLS (45 Registered)
                              │
                     KNOWLEDGE / CONTEXT (BusinessContextService)
                              │
                           EVIDENCE (UnifiedEvidenceItem)
                              │
                            POLICY (RiskLevel Gating)
                              │
                          APPROVALS (ActionApprovalService)
                              │
                         AUTOMATIONS (MrFoxMissionController)
                              │
                      AUDIT / OBSERVABILITY (MrFoxAuditService)
```

### Key Engine Components
1. **Core Agent**: `App\Domain\MrFox\Agent\MrFoxAgent` handles multi-turn reasoning, prompt boundary enforcement, and tool dispatching.
2. **Provider Router**: `App\Domain\MrFox\Providers\ProviderRouter` supporting OpenAI, Gemini, Anthropic, Groq, Ollama, and FakeAi (testing fallback).
3. **Tool Registry**: `App\Domain\MrFox\Tools\MrFoxToolRegistry` managing 45 domain-specific tools with RBAC and workspace checks.
4. **Context & Authorization**: `App\Domain\MrFox\Context\BusinessContextService` enforces user-level permissions so AI only sees data the caller is authorized to view.
5. **Governance & Risk Model**: `App\Domain\MrFox\Approvals\ActionApprovalService` enforces risk-level gating (`READ`, `DRAFT`, `LOW_RISK_WRITE`, `HIGH_RISK_EXTERNAL`, `FINANCIAL_MUTATION`).
6. **Action State Machine**: `proposed` ➔ `approved` ➔ `executing` ➔ `executed` (or `rejected` / `failed`) with cryptographic HMAC signatures.
7. **Evidence & Provenance**: `App\Domain\MrFox\Evidence\UnifiedEvidenceItem` classifies evidence as `STRONG`, `MEDIUM`, `WEAK`, or `CONFLICTED`.
8. **Audit & Usage**: `App\Domain\MrFox\Observability\MrFoxAuditService` redacting sensitive keys and `MrFoxUsageService` tracking token quotas per tenant.

---

## 3. REAL CRM JOURNEY TRACE

| Stage | Route | Controller Method | Model / Table | RBAC Permission | Frontend Surface | Test Coverage Status |
|---|---|---|---|---|---|---|
| **Login** | `GET/POST /login` | `Auth\AuthController::store` | `User` / `users` | Guest / Authenticated | `Auth/Login.tsx` | `WORKING` (`AuthenticationTest.php`) |
| **Dashboard** | `GET /crm` | `CrmController::dashboard` | `CrmLead`, `CrmDeal` | `crm.view` | `CRM/Dashboard.tsx` | `WORKING` (`CrmPopulatedActivityTest.php`) |
| **Lead Index** | `GET /crm/leads` | `CrmController::index` | `CrmLead` | `crm.view` | `CRM/Index.tsx` | `WORKING` (`CrmWorkflowTest.php`) |
| **Create Lead** | `POST /crm/leads` | `CrmController::storeLead` | `CrmLead` | `crm.manage` | `CRM/Components/LeadModal.tsx` | `WORKING` (`CrmWorkflowTest.php`) |
| **Move Stage** | `PATCH /crm/leads/{lead}/stage` | `CrmController::moveLead` | `CrmLead` | `crm.manage` | `CRM/PipelineView.tsx` | `WORKING` (`CrmWorkflowTest.php`) |
| **Convert Lead** | `POST /crm/leads/{lead}/convert` | `CrmController::convertLead` | `CrmLead` ➔ `AccountCustomer` + `CrmDeal` | `crm.manage` | `CRM/ConvertLeadModal.tsx` | `WORKING` (`CrmWorkflowTest.php`) |
| **Deals / Pipeline** | `POST /crm/deals` | `CrmController::storeDeal` | `CrmDeal`, `CrmStage` | `crm.manage` | `CRM/PipelineView.tsx` | `WORKING` (`CrmWorkflowTest.php`) |
| **Notes & Activities** | `POST /crm/notes` | `CrmController::storeNote` | `CrmNote` | `crm.manage` | `CRM/LeadDetail.tsx` | `WORKING` (`CrmPopulatedActivityTest.php`) |
| **Proposals** | `POST /sales/proposals` | `SalesProposalController::store` | `SalesProposal` | `sales.manage` | `Sales/ProposalModal.tsx` | `WORKING` (`SalesProposalTest.php`) |
| **Invoices** | `POST /sales/invoices` | `SalesInvoiceController::store` | `SalesInvoice` | `sales.manage` | `Sales/InvoiceModal.tsx` | `WORKING` (`SalesInvoiceTest.php`) |
| **Payments** | `POST /accounting/payments` | `AccountingController::storePayment` | `AccountPayment` | `accounting.manage` | `Accounting/PaymentModal.tsx` | `WORKING` (`PaymentIdempotencyTest.php`) |
| **Projects & Tasks** | `POST /taskly/tasks` | `TasklyController::storeTask` | `TasklyTask` | `tasks.manage` | `Taskly/Board.tsx` | `WORKING` (`TasklyWorkflowTest.php`) |
| **Communications** | `GET /communications` | `UnifiedInboxController::index` | `CommunicationMessage` | `communications.view` | `Communications/Inbox.tsx` | `WORKING` (`UnifiedCommunicationsSecurityTest.php`) |
| **Mr Fox Chat** | `POST /mrfox/chat` | `MrFoxChatController::chat` | `MrFoxConversation`, `MrFoxMessage` | Authenticated + Tenant Context | `MrFox/ChatWidget.tsx` | `WORKING` (`MrFoxArchitectureTest.php`) |

---

## 4. END-TO-END MR FOX EXECUTION TRACE

```
USER PROMPT ("Show leads needing follow-up today")
  │
  ▼
App\Http\Controllers\MrFoxChatController::chat()
  │ [Validates request, checks tenant context]
  ▼
App\Domain\MrFox\Agent\MrFoxAgent::process()
  │
  ├──► App\Domain\MrFox\Context\BusinessContextService::build()
  │      └─ Hydrates caller RBAC, organization_id, workspace_id, active modules
  │
  ├──► App\Domain\MrFox\Tools\MrFoxToolRegistry::getAvailableToolsForUser()
  │      └─ Filters registered tools against caller permissions & enabled modules
  │
  ├──► App\Domain\MrFox\Providers\ProviderRouter::dispatch()
  │      └─ Routes prompt to OpenAI / Gemini / Anthropic with tool definitions
  │
  ▼ [LLM returns tool call: CrmSearchLeadsTool]
App\Domain\MrFox\Tools\CrmSearchLeadsTool::execute()
  │      └─ Enforces workspace scope: CrmLead::forWorkspace($orgId, $workspaceId)
  │
  ▼ [Returns lead JSON payload]
App\Domain\MrFox\Evidence\UnifiedEvidenceItem::create()
  │      └─ Tags tool output with STRONG evidence level & provenance
  │
  ▼ [If write or external action requested]
App\Domain\MrFox\Approvals\ActionApprovalService::propose()
  │      └─ Creates MrFoxActionProposal with HMAC hash signature & RiskLevel
  │
  ▼ [If proposed action requires user approval]
State: PROPOSED ➔ User approves in UI ➔ Cryptographic signature verified ➔ State: EXECUTING ➔ Domain Action Executed ➔ State: EXECUTED
  │
  ▼
App\Domain\MrFox\Observability\MrFoxAuditService::log()
  └─ Writes audit trail with redacted credentials & token usage tracking
```

---

## 5. VERIFIED GATES & SUITE STATUS

- **Mr Fox Architecture & Security Suite**: 34/34 tests PASSING (100%)
- **CRM Workflow & Activity Suite**: 9/9 tests PASSING (100%)
- **Authentication & Security Suite**: 8/8 tests PASSING (100%)
- **Combined Target Baseline**: 43/43 tests PASSING (100%), 225 assertions
- **P0 Security Vulnerabilities**: 0
- **P1 Functional Blockers**: 0
- **Frontend Production Build**: Clean Vite production bundle compiled (`public/build`)
