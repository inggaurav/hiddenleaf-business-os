# Gideon & Mr. Fox — Duplicate Capabilities & Rationalization Report

**Target Baseline**: `hiddenleaf-business-os` on `develop/hiddenleaf-v2`  
**Date**: 2026-08-15  

---

## 1. Inventory of Duplicate & Overlapping Subsystems

### 1.1 Action Execution & Approvals
- **Gideon Implementation**: Multi-stage approval topology in PHP/Livewire domain (`PhaseEightApprovalGovernance`).
- **Mr. Fox Implementation**: `ActionApprovalService` with pessimistic row-locking (`lockForUpdate()`), SHA-256 payload integrity hashing (`payload_hash`), 24h expiration, and execution-time RBAC re-verification.
- **Winner**: **Mr. Fox** (Execution Security) + **Gideon** (Topology).
- **Reason**: Mr. Fox prevents race conditions and data tampering at the database level, while Gideon provides configurable multi-stage approver roles.
- **Migration Path**: Mr. Fox approval engine is the canonical executor; approval requests use Mr. Fox's SHA-256 hashing.

### 1.2 Tool Schemas & Input Validation
- **Gideon Implementation**: Dynamic TypeScript JSON-schema validation in `gideon-core`.
- **Mr. Fox Implementation**: Universal `ToolInputValidator` in Laravel gateway enforcing JSON schemas and stripping privileged authority keys (`organization_id`, `workspace_id`, `is_super_admin`).
- **Winner**: **Mr. Fox**.
- **Reason**: Server-side enforcement directly in Laravel guarantees prompt injection attacks cannot manipulate tenant context.

### 1.3 CRM & ERP Tooling
- **Gideon Implementation**: Legacy RISE CRM tools using HMAC bridge.
- **Mr. Fox Implementation**: 21 native tools calling canonical Laravel domain services (`AccountReportService`, `FinancialBalanceService`, `LedgerService`, `InventoryBalanceService`).
- **Winner**: **Mr. Fox**.
- **Reason**: Mr. Fox operates directly on canonical ERP models with zero HTTP bridge overhead and 100% financial calculation accuracy.
- **Retirement Plan**: Deprecate CodeIgniter `Parnika_Gideon` HMAC plugin.

### 1.4 AI Provider Abstraction
- **Gideon Implementation**: TypeScript multi-provider router supporting OpenAI, Anthropic, Gemini, Groq, Ollama, OpenRouter.
- **Mr. Fox Implementation**: Laravel provider router with OpenAI, Gemini, Anthropic, Groq, Ollama, fake providers, and workspace settings resolution.
- **Winner**: **Unified Mr. Fox Router** (incorporating Gideon's provider coverage).
- **Reason**: Provides unified workspace configuration in Laravel while supporting all external AI models.

### 1.5 Chat Persistence & Multi-Tenancy
- **Gideon Implementation**: In-memory session store in `gideon-memory`.
- **Mr. Fox Implementation**: Database-backed `mrfox_conversations` and `mrfox_messages` with multi-tenant foreign keys, composite indexes, and IDOR protection.
- **Winner**: **Mr. Fox**.
- **Reason**: Production-grade relational persistence and strict tenant isolation.
