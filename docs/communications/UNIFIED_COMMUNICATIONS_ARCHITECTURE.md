# Unified Communications Architecture — HiddenLeaf Business OS

**Host Platform**: `hiddenleaf-business-os` on `develop/hiddenleaf-v2`  
**Date**: 2026-08-15  
**Canonical Identity**: **Mr. Fox Unified Communications Layer**  

---

## 1. Architectural Overview

The Unified Communications Layer unifies all external customer and team interaction channels into a single normalized multi-tenant relational domain:

```text
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           EXTERNAL CHANNELS                            │
  │   Gmail API  │  WhatsApp Cloud  │  Slack Events  │  Meta  │  Internal  │
  └───────┬──────────────┬──────────────────┬────────────┬─────────┬───────┘
          │              │                  │            │         │
          ▼              ▼                  ▼            ▼         ▼
  ┌────────────────────────────────────────────────────────────────────────┐
  │                      PROVIDER ADAPTERS LAYER                           │
  │   GmailProvider  │  WhatsAppCloudProvider  │  SlackProvider  │ etc.    │
  │   • Token Encryption    • 24h Customer Window • Replay Window (300s)   │
  └──────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
  ┌────────────────────────────────────────────────────────────────────────┐
  │                 INGESTION, CLASSIFICATION & MATCHING                   │
  │   • InteractionClassificationEngine (Deterministic Sentiment/Intent)   │
  │   • AttentionPriorityCalculator (0-100 Attention Priority Score)       │
  │   • IdentityMatcher (Verified Email & Normalized E.164 Phone to CRM)   │
  └──────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
  ┌────────────────────────────────────────────────────────────────────────┐
  │                    CANONICAL COMMUNICATIONS DOMAIN                     │
  │   • comm_accounts      • comm_conversations    • comm_messages         │
  │   • comm_participants  • comm_attachments                              │
  └──────────────────────────────┬─────────────────────────────────────────┘
                                 │
               ┌─────────────────┴─────────────────┐
               ▼                                   ▼
  ┌─────────────────────────┐         ┌──────────────────────────────┐
  │    UNIFIED INBOX UI     │         │     MR. FOX INTELLIGENCE     │
  │ • 3-Column Desktop      │         │ • communications.summarize   │
  │ • Mobile Drawer View    │         │ • communications.urgent      │
  │ • Channel Filters       │         │ • communications.draft.reply │
  │ • CRM Quick-Link        │         │ • communications.send.reply  │
  └─────────────────────────┘         └──────────────┬───────────────┘
                                                     │
                                                     ▼
                                      ┌──────────────────────────────┐
                                      │ ACTION APPROVAL & ROW-LOCK   │
                                      │ • SHA-256 Payload Hash       │
                                      │ • Outbound Idempotency Key   │
                                      │ • Double-Send Prevention     │
                                      └──────────────────────────────┘
```

---

## 2. Ingestion & Security Guardrails

1. **OAuth Security & Secret Storage**:
   - `CommunicationAccount` encrypts OAuth access tokens and refresh tokens at rest via Laravel `Crypt`.
   - Never exposes decrypted secrets over API endpoints.

2. **Webhook Verification**:
   - **WhatsApp**: Meta Hub challenge verification and SHA-256 HMAC signature verification (`x-hub-signature-256`).
   - **Slack**: Signing secret validation with 300-second timestamp replay attack window (`x-slack-request-timestamp`).
   - **Meta**: Signature check (`x-hub-signature-256`).
   - **Payload Bounds**: Enforces 2MB maximum payload size.

3. **Outbound Idempotency & Concurrency Safety**:
   - `CommunicationSendService` utilizes database transactions and pessimistic row-locking (`lockForUpdate()`) on `comm_conversations` and `comm_accounts`.
   - Idempotency keys (`idempotency_key`) guarantee that rapid re-submissions or queue retries execute exactly once.

---

## 3. Mr. Fox Executive Communication Intelligence

Mr. Fox provides 7 grounded operational tools operating above the communication domain:
1. `communications.search`: Semantic and keyword search across all channels.
2. `communications.get`: Full thread retrieval with message history.
3. `communications.unread.summary`: Executive count and channel distribution of unread messages.
4. `communications.urgent.summary`: High-priority threads ($\ge 75$ score) needing immediate response.
5. `communications.summarize`: Transcript summarization with action items.
6. `communications.draft.reply`: Brand Profile grounded draft responses evaluated by `ContentReviewEngine`.
7. `communications.send.reply`: Approved outbound execution guarded by `ActionApprovalService` (RiskLevel::HIGH).
