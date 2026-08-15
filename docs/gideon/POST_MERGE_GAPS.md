# Post-Gideon Integration Roadmap & Gaps

**Target Baseline**: `hiddenleaf-business-os` on `develop/hiddenleaf-v2`  
**Date**: 2026-08-15  

---

## 1. Genuinely Missing Capabilities

With the Gideon audit, code inventory, capability matrix, and core component integration completed, the following capabilities represent genuine remaining roadmap items:

### 1.1 Unified Communications & Inbox
- **Status**: **GENUINE GAP (Next Target)**
- **Scope**:
  - **Gmail / Google Workspace Integration**: Real-time sync via Gmail API, OAuth token lifecycle, thread parsing, inbound email classification, AI response drafting.
  - **WhatsApp Cloud API Integration**: Direct Meta Business API connector, webhook verification, media attachments, template messaging, automated customer service routing.
  - **Slack App / Bot Connector**: Workspace bot token integration, channel mentions, direct messages, Mr. Fox interactive actions via Slack block kit.
  - **Unified Inbox UI**: Single master inbox interface in HiddenLeaf combining Email, WhatsApp, Slack, and Social comments with SLA timers and assignment.

### 1.2 End-to-End Visual Automation Workflow Builder
- **Status**: **GENUINE GAP**
- **Scope**:
  - Trigger-Condition-Action visual flow designer (e.g. "When an invoice is 3 days overdue $\rightarrow$ draft WhatsApp payment reminder $\rightarrow$ notify account manager").
  - Autonomous mission scheduling and retry policies.

### 1.3 Full WebRTC Voice Interface in Web UI
- **Status**: **FOUNDATION READY (Service Exists, UI Hookup Needed)**
- **Scope**:
  - LiveKit WebRTC client integration into `MrFoxPanel.tsx`.
  - Push-to-talk, bidirectional voice streaming, and orb animation state synchronization with backend `gideon-voice-agent`.

---

## 2. Capability Status Summary

| Area | Status After Merge | Next Step |
|---|---|---|
| **ERP Operations & Intelligence** | COMPLETE | Production verified |
| **Governed Knowledge & Citations** | INTEGRATED | Add PDF/DOCX file upload UI in Knowledge module |
| **Skills & Marketing Studio** | INTEGRATED | Add Marketing Studio frontend page |
| **Brand Profiles Domain** | INTEGRATED | Add Brand Profile settings form in Admin UI |
| **Multi-Provider AI Routing** | COMPLETE | OpenAI, Anthropic, Gemini, Groq, Ollama supported |
| **Action Approvals Security** | HARDENED | Pessimistic locking & SHA-256 integrity active |
| **Unified Communications Inbox** | **NEXT PHASE** | Implement Gmail, WhatsApp, and Slack connectors |
