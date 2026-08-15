<?php

namespace App\Domain\Communications\Matching;

class InteractionClassificationEngine
{
    /**
     * Deterministic classification for any communication interaction.
     *
     * @return array{sentiment: string, intent: string, urgency: string, reply_need: string, risk: string}
     */
    public function classify(string $body, string $channel = 'email'): array
    {
        $bodyLower = mb_strtolower($body);

        $sentiment = 'neutral';
        $intent = 'other';
        $urgency = 'low';
        $replyNeed = 'optional';
        $risk = 'none';

        // 1. Complaint / Negative keywords
        if (preg_match('/\b(broken|terrible|worst|refund|scam|disappointed|horrible|useless|fail|sucks|angry|frustrated|overdue|late)\b/i', $bodyLower)) {
            $sentiment = 'negative';
            $intent = 'complaint';
            $urgency = 'high';
            $replyNeed = 'required';
        }

        // 2. Questions / Inquiries / Purchase Intent
        if (str_contains($body, '?') || preg_match('/\b(how|why|when|where|what|cost|price|available|buy|discount|order|invoice|quote|proposal|contract)\b/i', $bodyLower)) {
            if ($intent === 'other') {
                $intent = 'question';
                $replyNeed = 'recommended';
                $urgency = 'medium';
            }
            if (preg_match('/\b(price|cost|buy|discount|order|quote|proposal|purchase)\b/i', $bodyLower)) {
                $intent = 'purchase_interest';
                $replyNeed = 'required';
                $urgency = 'high';
            }
        }

        // 3. Praise / Positive
        if (preg_match('/\b(love|awesome|great|amazing|best|thank|thanks|good|fantastic|excellent|pleasure)\b/i', $bodyLower)) {
            if ($sentiment !== 'negative') {
                $sentiment = 'positive';
                if ($intent === 'other') {
                    $intent = 'praise';
                }
                $replyNeed = 'optional';
            }
        }

        // 4. Spam / Phishing Links
        if (preg_match('/\b(crypto|free cash|lottery|winner|wire money|western union)\b/i', $bodyLower)) {
            $intent = 'spam';
            $replyNeed = 'no_reply';
            $urgency = 'low';
        }

        // 5. Legal / Brand / Urgent Safety Escalation (Deterministic override)
        if (preg_match('/\b(lawyer|sue|legal|police|court|scam|fraud|threat|attorney|breach)\b/i', $bodyLower)) {
            $risk = 'legal_risk';
            $urgency = 'critical';
            $replyNeed = 'required';
        }

        return [
            'sentiment' => $sentiment,
            'intent' => $intent,
            'urgency' => $urgency,
            'reply_need' => $replyNeed,
            'risk' => $risk,
        ];
    }
}
