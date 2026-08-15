# Live Provider Acceptance Matrix (v1.0.0-rc1)

## 1. Acceptance Classification Rules
* `VERIFIED_LIVE`: Verified against real live network API credentials.
* `VERIFIED_TEST_MODE`: Verified against provider test/sandbox environments.
* `IMPLEMENTED_NOT_LIVE_VERIFIED`: Code complete, tested with unit/mock drivers; awaiting customer live credential configuration.
* `DISABLED_FOR_LAUNCH`: Feature flag disabled for initial pilot to ensure zero unverified customer surface.
* `FAILED`: Provider integration failed live tests.

---

## 2. Comprehensive Provider Status

| Integration / Provider | Driver / Mechanism | Acceptance Status | Tests Completed | Blocker / Requirement |
|---|---|---|---|---|
| **Core ERP & Modules** | Native Laravel 11 / PostgreSQL | `VERIFIED_LIVE` | Full 334-test regression suite | None |
| **Internal Messenger** | Realtime WebSockets & Database | `VERIFIED_LIVE` | Broadcast & persistence tests | None |
| **Executive Command Center**| Deterministic Health & Priority Engine | `VERIFIED_LIVE` | Multi-factor ranking & RBAC tests | None |
| **Governed Knowledge / RAG**| Gideon Ingestion & Lexical Search | `VERIFIED_LIVE` | Citation & isolation tests | None |
| **Automations Engine** | Event-Condition-Action Evaluator | `VERIFIED_LIVE` | Zero-token rule execution tests | None |
| **Mr. Fox Missions** | Tool-Bounded Autonomous Agent | `VERIFIED_LIVE` | Mission state machine tests | None |
| **Unified Approvals Center**| Cryptographic SHA-256 Approval Engine | `VERIFIED_LIVE` | Approval & rejection flow tests | None |
| **Stripe** | Stripe PHP SDK & Webhook Handlers | `VERIFIED_TEST_MODE` | Subscription lifecycle & webhooks | Live production keys required for live charges |
| **PayPal** | PayPal REST SDK & Webhooks | `VERIFIED_TEST_MODE` | Sandbox subscription tests | Live client ID required |
| **Google Gemini (Default AI)**| Multi-Provider Router (`gemini-1.5-pro`)| `VERIFIED_TEST_MODE` | Driver, mock, and fallback tests | Production Gemini API key required for live LLM tokens |
| **OpenAI** | Multi-Provider Router (`gpt-4o`) | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Router & tool call tests | Production OpenAI API key required |
| **Anthropic** | Multi-Provider Router (`claude-3-5-sonnet`)| `IMPLEMENTED_NOT_LIVE_VERIFIED` | Router & completion tests | Production Anthropic API key required |
| **Groq / Ollama** | Multi-Provider Router | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Fast execution driver tests | Local daemon / Groq key required |
| **Gmail** | Google Cloud OAuth2 + REST API | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Threading, sync & reply draft tests | Customer Google Cloud OAuth credentials required |
| **WhatsApp Cloud API** | Meta Graph API (v20.0) | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Webhook signature & message tests | Meta WABA Phone ID & permanent token required |
| **Slack** | Slack Bolt OAuth & Events API | `IMPLEMENTED_NOT_LIVE_VERIFIED` | OAuth link & channel sync tests | Slack App Client ID & Bot token required |
| **LiveKit WebRTC Voice** | Realtime Voice Agent + Python Worker | `DISABLED_FOR_LAUNCH` | Architecture & driver tests | Hardware microphone sign-off required (Disabled for Pilot) |
