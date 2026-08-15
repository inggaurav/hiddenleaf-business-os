# Gideon & Mr. Fox — Capability Comparison & Decision Matrix

**Product Identity**: **Mr. Fox** (Executive Intelligence & Business Operating System)  
**Target Repository**: `hiddenleaf-business-os` on `develop/hiddenleaf-v2`  
**Date**: 2026-08-15  

---

## Capability Matrix

| Capability Category | Gideon Implementation | Mr. Fox Implementation | Stronger Implementation | Decision | Rationale |
|---|---|---|---|---|---|
| **Top-Level Identity & Branding** | "Gideon" in RISE/Stackposts | "Mr. Fox" throughout HiddenLeaf | **Mr. Fox** | `KEEP_MRFOX` | Mr. Fox is the canonical product name and executive assistant persona for HiddenLeaf Business OS. |
| **ERP Operations & Grounding** | Legacy RISE CRM tools via HMAC bridge | 21 native tools grounded in canonical domain services (`AccountReportService`, `LedgerService`, `InventoryBalanceService`) | **Mr. Fox** | `KEEP_MRFOX` | Mr. Fox directly executes tenant-scoped Laravel services without unnecessary HTTP bridge overhead. |
| **Action Approval Security** | Flexible multi-stage approval topology in PHP domain | Row-locking (`lockForUpdate`), SHA-256 integrity hashing, execution-time RBAC, atomic transitions | **Mr. Fox** (Execution) / **Gideon** (Topology) | `MERGE` | Keep Mr. Fox's hardened row-lock and hash security while adopting Gideon's multi-stage approval topology (Sequential, Role-based, Regulatory). |
| **Tool Input Validation** | JSON schema parsing in TypeScript | Universal `ToolInputValidator` with authority key stripping, type casting, bounds | **Mr. Fox** | `KEEP_MRFOX` | Prevents prompt injection and privileged parameter spoofing directly at the Laravel gateway. |
| **Multi-Provider AI Routing** | Multi-provider router in `gideon-ai` (OpenAI, Anthropic Claude, Gemini, Groq, Ollama, OpenRouter) with streaming & backoff | OpenAI, Gemini, Fake router with settings resolution | **Gideon** | `ADAPT_GIDEON` | Adapt Gideon's multi-provider router capabilities (Anthropic, Groq, Ollama, OpenRouter, streaming SSE) into Mr. Fox provider router. |
| **Knowledge Ingestion & RAG** | `gideon-knowledge` parsing PDF, DOCX, MD, TXT, HTML, CSV, JSON, token chunking, hybrid search, reranking, versioning, rollback, ACL | Basic prompt context injection | **Gideon** | `REUSE_GIDEON` | Gideon's knowledge system is complete, robust, and mature. Expose via clean internal service / adapter in Mr. Fox. |
| **Vector Storage** | Qdrant / pgvector vector ports with hybrid indexing | None | **Gideon** | `REUSE_GIDEON` | Reuse Gideon's vector search ports and embeddings pipeline for document intelligence. |
| **Evidence & Citations** | Document citation DTOs (document, page, chunk, snippet, freshness) | ERP record evidence chips (`invoice`, `bill`, `lead`, `product`, `task`, `leave`, `bank_account`) | **Complementary** | `MERGE` | Unify into a shared `EvidenceItem` contract supporting both ERP business records and knowledge document citations. |
| **Skills Framework** | `gideon-skills` manifest schema, registry, and execution kernel (Social, SEO, Ads, Blog, Email, Repurpose) | Hardcoded ERP tools | **Gideon** | `ADAPT_GIDEON` | Adapt Gideon's declarative skill manifests and execution engine into Mr. Fox tool registry. |
| **Brand Profiles** | Comprehensive domain schema in `Mr. Fox OS` (Identity, Voice, Audience, Guardrails, Visuals, Snapshots) | Plain string system prompt | **Gideon** | `ADAPT_GIDEON` | Integrate Gideon's Brand Profiles schema into HiddenLeaf as a first-class tenant asset for AI operations. |
| **Marketing Studio** | Full content creation pipelines (Social, Meta/Google Ads, SEO Blogs, Email, Repurposing) | None | **Gideon** | `ADAPT_GIDEON` | Expose Gideon Marketing Studio capabilities through Mr. Fox skill execution and UI. |
| **Content Review Engine** | 0-100 threshold scoring engine (Brand Voice, Compliance, Policy, Factuality) | None | **Gideon** | `ADAPT_GIDEON` | Integrate review scoring engine into Mr. Fox action and publication workflows. |
| **Missions & Autonomous Goals** | Multi-step autonomous goals, execution plans, and task monitors in `Mr. Fox OS` | Single-turn agent requests | **Gideon** | `ADAPT_GIDEON` | Adapt Gideon Missions into Mr. Fox's future background goal orchestration without creating competing workflow engines. |
| **Realtime Voice Agent** | LiveKit WebRTC worker, Gemini Live streaming, STT/TTS pipeline, VAD, barge-in | None | **Gideon** | `REUSE_GIDEON` | Retain Python/LiveKit voice agent as a backend microservice called through Mr. Fox API. |
| **Conversation Memory** | Persistent `mrfox_conversations` & `mrfox_messages` with tenant isolation + token tracking | In-memory / session store in `gideon-memory` | **Mr. Fox** | `KEEP_MRFOX` | Mr. Fox's database-backed, tenant-isolated conversation persistence is authoritative. |
| **Social Inbox & Communications** | Multi-platform ingestion (Meta, LinkedIn, TikTok, Twitter/X), intent classifier, reply generator | None | **Gideon** | `ADAPT_GIDEON` | Adapt Gideon Social Inbox ingestion and reply drafting engines into HiddenLeaf communications. |
| **Email / Gmail / WhatsApp / Slack Connectors** | Basic settings in WorkDo, social connectors in Gideon | Partial setting stubs | **Neither Complete** | `MISSING_BOTH` | Unified Inbox with full Gmail API, WhatsApp Cloud API, and Slack integration remains the next phase after Gideon integration. |
| **Legacy RISE HMAC Bridge** | `Parnika_Gideon` CodeIgniter plugin | Native Laravel service calls | **Mr. Fox** | `RETIRE_DUPLICATE` | Retire legacy RISE CodeIgniter HMAC bridge since HiddenLeaf runs native Laravel ERP. |
| **UI Presentation** | Slide-over drawer with evidence chips, approval cards, and quick prompts | Separate standalone Gideon page | **Mr. Fox** | `KEEP_MRFOX` | Keep unified slide-over drawer `MrFoxPanel.tsx` and executive dashboard widget for seamless UX across all ERP screens. |
