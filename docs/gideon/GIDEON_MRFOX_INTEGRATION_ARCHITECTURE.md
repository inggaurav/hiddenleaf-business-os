# Gideon & Mr. Fox — Unified Integration Architecture

**Canonical Product Identity**: **Mr. Fox Executive Intelligence Layer**  
**Host Platform**: `hiddenleaf-business-os`  
**Target Branch**: `develop/hiddenleaf-v2`  
**Date**: 2026-08-15  

---

## 1. Unified Architecture Topology

```text
                     HiddenLeaf Business OS
                      (Web & API Interface)
                               │
                               ▼
                       MR. FOX AGENT
            (Central Business Intelligence Layer)
                               │
    ┌──────────────────────────┼──────────────────────────┐
    ▼                          ▼                          ▼
ERP Core Tools         Governed Knowledge         Skills & Intelligence
• Financial Reports    • Document Ingestion       • Social Media Posts
• Ledger & Cash Flow   • Lexical & Vector RAG     • Meta / Google Ads
• Invoices & Bills     • Multi-Tenant ACL         • SEO Blog Outlines
• Inventory Stock      • Citations & Freshness    • Email Copywriting
• CRM Leads & Deals    • Document Versioning      • Content Repurposing
• Tasks & Headcount                               • Automated 0-100 Review
    │                          │                          │
    └──────────────────────────┼──────────────────────────┘
                               ▼
                 Action Approval & Security Gate
                 • Row-Level Pessimistic Locking
                 • SHA-256 Payload Integrity Hash
                 • Multi-Tenant Workspace Isolation
                 • Execution-Time RBAC Re-check
                               │
                               ▼
                    Multi-Provider AI Router
         (OpenAI, Anthropic Claude, Gemini, Groq, Ollama)
```

---

## 2. Responsibilities & Service Boundaries

### 2.1 Top-Level Orchestration: Mr. Fox
- **MrFoxAgent** is the single entry point and authority for all business reasoning, multi-turn tool loops, and action execution.
- Evaluates user intent, passes requests through the **ToolInputValidator** (stripping privileged authority keys such as `organization_id` or `workspace_id`), and compiles execution context.
- High-risk operations (e.g. creating records, approving payments, running financial mutations) are automatically captured into **MrFoxActionProposal** records requiring user approval.

### 2.2 Reused Gideon Capabilities
- **Governed Knowledge Layer**: Multi-tenant document indexing, semantic/lexical chunk search, page-level citation extraction, and policy Q&A grounded in verified organizational manuals.
- **Skills Framework**: Declarative YAML/JSON skill manifests with input/output contracts for automated marketing, SEO, and copywriting tasks.
- **Brand Profiles**: First-class tenant domain asset maintaining tone of voice archetypes, audience personas, value propositions, and compliance safety rules.
- **Content Review Engine**: Pre-flight scoring engine (0-100 threshold) inspecting generated copy for compliance breaches, discouraged words, and engagement clarity.
- **Real-Time Voice Architecture**: Python/LiveKit WebRTC microservice for bidirectional low-latency audio, Gemini Live streaming, and instant speech interruption / barge-in.

---

## 3. Unified Evidence Contract

All responses returned by Mr. Fox—whether from ERP database queries, knowledge document citations, or brand profiles—conform to the **UnifiedEvidenceItem** schema:

```json
{
  "type": "knowledge_chunk",
  "id": 14,
  "label": "Alpha Remote Work & Expense Policy (p. 4)",
  "route": "/knowledge",
  "snippet": "Employees can claim up to $150 per month for home internet...",
  "page_number": 4,
  "document_id": "1",
  "score": 0.95,
  "authority": "governed_knowledge",
  "freshness": "2026-08-15T07:30:00Z"
}
```

---

## 4. Multi-Provider Router Specification

| Provider | Supported Models | Primary Use Case |
|---|---|---|
| **OpenAI** | `gpt-4o`, `gpt-4o-mini` | Complex ERP analysis, structured tool calling |
| **Anthropic** | `claude-3-5-sonnet`, `claude-3-5-haiku` | Deep synthesis, long-context document analysis |
| **Gemini** | `gemini-1.5-pro`, `gemini-1.5-flash` | Multimodal analysis, real-time live streaming |
| **Groq** | `llama-3.3-70b-versatile` | Ultra-low-latency real-time responses (<300ms) |
| **Ollama** | `llama3.2`, `mistral` | On-premise air-gapped / privacy-first deployments |

---

## 5. Security & Isolation Invariants

1. **Multi-Tenancy**: All knowledge documents, chunks, brand profiles, and conversations are keyed to `organization_id` and `workspace_id` with foreign key integrity.
2. **Action Immutability**: Action proposals use SHA-256 payload hashing and `lockForUpdate()` pessimistic locking during execution to eliminate race conditions.
3. **Secret Redaction**: `MrFoxAuditService` scrubs Bearer tokens, OpenAI/Anthropic/Stripe API keys, and passwords before persisting audit logs.
4. **Token Quotas**: Monthly workspace token ceilings prevent runaway API consumption.
