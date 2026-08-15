# Live Provider Acceptance Matrix

## Acceptance Classifications
* `VERIFIED_LIVE`: Verified against real live network API credentials.
* `IMPLEMENTED_NOT_LIVE_VERIFIED`: Code complete, tested with unit/mock drivers; awaiting customer live credential configuration.
* `FAILED`: Integration failed live tests.
* `NOT_CONFIGURED`: Optional provider not yet configured in `.env`.
* `NOT_APPLICABLE`: Provider not required for core launch.

---

## Provider Status Matrix

| Integration / Provider | Driver / Architecture | Live Verification Status | Notes |
|---|---|---|---|
| **Stripe** | Webhook signature, subscription checkout, customer portal | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Production Stripe Secret & Webhook Secret required |
| **PayPal** | Subscriptions API & Webhooks | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Production Client ID & Secret required |
| **OpenAI** | `OpenAiProvider` (gpt-4o, text-embedding-3-small) | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Tested with driver; requires production OpenAI Key |
| **Google Gemini** | `GeminiProvider` (gemini-1.5-pro, gemini-1.5-flash) | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Tested with driver; requires production Gemini Key |
| **Anthropic** | `AnthropicProvider` (claude-3-5-sonnet) | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Tested with driver; requires production Anthropic Key |
| **Groq** | `GroqProvider` (llama-3.1-70b-versatile) | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Tested with driver; requires production Groq Key |
| **Ollama** | `OllamaProvider` (local open models) | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Local daemon connectivity required |
| **Gmail** | Google Cloud OAuth2 + Gmail REST API | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Customer Google OAuth Client required |
| **WhatsApp** | Meta Graph API (v20.0) Cloud Business API | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Meta WABA Phone ID & System Token required |
| **Slack** | Slack Bolt OAuth & Web API | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Slack App Client ID & Bot Token required |
| **LiveKit Voice** | LiveKit WebRTC Realtime + Python Worker | `IMPLEMENTED_NOT_LIVE_VERIFIED` | LiveKit Server URL & API Secret required |
| **Gideon RAG** | TypeScript runtime + Lexical search | `IMPLEMENTED_NOT_LIVE_VERIFIED` | Node.js runtime process required |
| **Internal Messenger** | Real-time internal user communication | `VERIFIED_LIVE` | Built-in database & broadcast channels |
