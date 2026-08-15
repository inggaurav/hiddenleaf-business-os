# Gideon & Mr. Fox Ecosystem — Source Inventory

**Target Baseline**: `hiddenleaf-business-os` on `develop/hiddenleaf-v2`  
**Date**: 2026-08-15  

---

## 1. Discovered Gideon Codebases

### Codebase 1: Gideon Runtime (TypeScript Monorepo)
- **Path**: `C:\Users\manag\Pictures\RISE-Ultimate-Project-Manager-and-CRM\services\gideon-runtime`
- **Language / Runtime**: TypeScript 5.x / Node.js 20+ (pnpm / turborepo workspace)
- **Role**: High-performance AI runtime engine, Knowledge Ingestion & Hybrid RAG, Multi-Provider Router, Skills Kernel, Prompt Intelligence, and Streaming Service.
- **Status**: **ACTIVE / MATURE**
- **Packages & Modules**:
  - `packages/gideon-core`: Base architecture, DTOs, provider registry, ID generators, error domains, secret redaction, telemetry.
  - `packages/gideon-ai`: Multi-provider router supporting OpenAI, Anthropic Claude, Google Gemini, Groq, Ollama, OpenRouter with streaming, fallback, and function calling.
  - `packages/gideon-knowledge`: Multi-format parsing (PDF, DOCX, Markdown, Text, HTML, CSV, JSON), tokenization/chunking, lexical/vector indexing, hybrid search with reciprocal rank fusion, document versioning, rollback, audit trails, and multi-tenant authorization ACL.
  - `packages/gideon-skills`: Declarative skills manifest registry, schema validation, and execution engine.
  - `packages/gideon-prompt-intelligence`: Prompt Studio engine, variable templating, model binding, and prompt optimization.
  - `packages/gideon-memory` & `gideon-memory-contracts`: Conversation, episodic, and semantic memory extractors.
  - `packages/gideon-orchestrator`: Specialist agent coordinator, multi-turn loops, barge-in, streaming SSE.

### Codebase 2: Gideon Voice Agent (Python / LiveKit)
- **Path**: `C:\Users\manag\Pictures\New folder (2)\stackposts_ai_app\services\gideon-voice-agent`
- **Language / Runtime**: Python 3.11+ / LiveKit WebRTC / asyncio
- **Role**: Real-time voice interaction, bidirectional streaming, STT/TTS pipeline, Voice Activity Detection (VAD), Gemini Live API, and barge-in / interruption handling.
- **Status**: **ACTIVE / PRODUCTION-READY**
- **Major Components**:
  - `agent.py`: Realtime LiveKit RTC worker.
  - Speech-to-Text: Deepgram / Whisper adapter.
  - Text-to-Speech: ElevenLabs / Cartesia / OpenAI TTS.
  - Interruption handler: Instant speech cut-off on user audio frame detection.

### Codebase 3: Mr. Fox OS & Stackposts Gideon Domain (PHP / Laravel / Livewire)
- **Path**: `C:\Users\manag\Pictures\New folder (2)\Mr. Fox OS` & `C:\Users\manag\Pictures\New folder (2)\stackposts_ai_app\modules\Gideon`
- **Language / Runtime**: PHP 8.2+ / Laravel 11 / Livewire 3
- **Role**: Business domain models, Brand Profiles, Marketing Studio, Review Engine, Social Inbox, Missions, and Multi-Stage Approval Governance.
- **Status**: **ACTIVE / HIGH VALUE**
- **Major Components**:
  - **Brand Profiles**: Comprehensive schema (`identity`, `tone_of_voice`, `positioning`, `target_audience`, `brand_safety_rules`, `visual_guidelines`, `snapshots`).
  - **Marketing Studio**: Content generation pipelines for Social Posts, Ads (Meta/Google), Blog/SEO articles, Email campaigns, and Content Repurposing.
  - **Review Engine**: Automated scoring engine (0-100 threshold, Brand Voice compliance, Policy/Safety verification, Factuality assertions).
  - **Social Inbox & Communications**: Unified interaction ingestion (Facebook, Instagram, LinkedIn, TikTok, Twitter/X), intent/sentiment classification, priority scoring, automated AI draft generation.
  - **Missions & Automation**: Autonomous goal-oriented missions, multi-step execution plans, recurring tasks, and telemetry.
  - **Approval Governance**: Configurable approval topology (Single, Role-based, Sequential, Parallel, Regulatory).

### Codebase 4: Parnika Gideon Bridge for RISE CRM (PHP / CodeIgniter)
- **Path**: `C:\Users\manag\Pictures\RISE-Ultimate-Project-Manager-and-CRM\plugins\Parnika_Gideon`
- **Language / Runtime**: PHP 8.x / CodeIgniter 3
- **Role**: Legacy HMAC bridge connecting RISE CRM to Gideon Runtime.
- **Status**: **LEGACY / RETIRABLE** (Superceded by HiddenLeaf's native Laravel domain services).

---

## 2. Current HiddenLeaf Mr. Fox Implementation

- **Path**: `c:\Users\manag\Documents\Mr. Fox\hiddenleaf-business-os`
- **Branch**: `develop/hiddenleaf-v2`
- **Language / Runtime**: PHP 8.2+ / Laravel 11 / React 19 / Inertia.js
- **Role**: Top-level product identity, executive intelligence, enterprise ERP operations, action approval security, multi-tenant RBAC, and business telemetry.
- **Status**: **VERIFIED / HARDENED** (308 passing tests, zero regressions).
- **Core Components**:
  - `app/Domain/MrFox/Agent/MrFoxAgent.php`: Central orchestrator.
  - `app/Domain/MrFox/Tools/`: 21 typed business tools grounded in canonical accounting, inventory, CRM, HRM, and project domain services.
  - `app/Domain/MrFox/Approvals/ActionApprovalService.php`: Row-locked (`lockForUpdate`), SHA-256 payload integrity hashed, execution-time permission-verified approval engine.
  - `app/Domain/MrFox/Validation/ToolInputValidator.php`: Server-side JSON schema and authority parameter validator.
  - `app/Domain/MrFox/Observability/`: Sanitized audit logging with regex secret scrubbing and monthly workspace token quota management.
  - `app/Models/MrFoxConversation.php` & `MrFoxMessage.php`: Persistent multi-tenant chat conversations with IDOR isolation.
  - `resources/js/Components/MrFox/MrFoxPanel.tsx`: Executive slide-over panel with tool execution indicators, grounded evidence chips, and approval cards.
