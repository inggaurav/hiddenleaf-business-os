# Gideon Social Inbox — Source Inventory & Analysis

**Source Directory**: `C:\Users\manag\Pictures\New folder (2)\Mr. Fox OS\SocialInbox`  
**Host Target**: `hiddenleaf-business-os` on `develop/hiddenleaf-v2`  
**Date**: 2026-08-15  

---

## 1. Discovered Components & Architecture

### 1.1 Ingestion & Processing Pipeline
- **`InteractionIngestionService.php`**: Handles normalization of incoming webhook payloads from social platforms into structured internal interaction records.
- **`InteractionClassificationEngine.php`**:
  - Deterministic keyword and regex rule classifier executing before any LLM inference.
  - Detects complaints, questions, purchase intent, praise, spam, and critical brand/legal risks.
  - Guarantees deterministic safety overrides always take precedence over AI enrichment.
- **`AttentionPriorityCalculator.php`**:
  - Scores urgency from 0 to 100 based on Sentiment (Negative +35), Intent (Complaint +30, Question +25, Purchase +25), Urgency (Critical +40, High +25), Risk (Legal/Brand +30), and Aging (>24h +20).
  - Clamped between 0 and 100.
- **`PlatformCapabilityRegistry.php`**:
  - Deployed capability matrix mapping support across Facebook, Instagram, LinkedIn, TikTok, Twitter/X, and YouTube.
- **`SocialReplyDraftGenerator.php`**:
  - Generates proposed comment/message replies grounded in the active **Brand Profile** tone of voice and persona.
  - Strict trust model: Generates proposals only; never publishes automatically without explicit user confirmation.

---

## 2. Reuse & Adaptation Plan for HiddenLeaf Unified Inbox

1. **Deterministic Classification Engine**: Adopt `InteractionClassificationEngine` into `App\Domain\Communications\Matching\InteractionClassificationEngine.php` to classify all inbound emails, WhatsApp messages, Slack mentions, and social DMs.
2. **Attention Priority Calculation**: Adopt `AttentionPriorityCalculator` into `App\Domain\Communications\Matching\AttentionPriorityCalculator.php` to provide unified urgency scoring across all channels.
3. **Brand-Aware Draft Reply Generator**: Connect `SocialReplyDraftGenerator` to `App\Domain\Communications\Actions\CommunicationReplyGenerator.php` using `MrFoxBrandProfile` and `ContentReviewEngine`.
4. **Canonical Provider Contracts**: Integrate `PlatformCapabilityRegistry` rules into `App\Domain\Communications\Contracts\CommunicationProviderContract.php`.
