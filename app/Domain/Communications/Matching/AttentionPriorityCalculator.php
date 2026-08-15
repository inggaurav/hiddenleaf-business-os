<?php

namespace App\Domain\Communications\Matching;

use Carbon\Carbon;

class AttentionPriorityCalculator
{
    /**
     * Calculate 0-100 Attention Priority Score.
     *
     * @param  array{sentiment?: string, intent?: string, urgency?: string, risk?: string, unread?: bool, last_message_at?: string|Carbon}  $context
     */
    public function calculate(array $context): int
    {
        $score = 50; // base score

        // Sentiment adjustment
        $sentiment = $context['sentiment'] ?? 'neutral';
        if ($sentiment === 'negative') {
            $score += 25;
        } elseif ($sentiment === 'positive') {
            $score -= 10;
        }

        // Intent adjustment
        $intent = $context['intent'] ?? 'other';
        if ($intent === 'complaint') {
            $score += 25;
        } elseif ($intent === 'purchase_interest') {
            $score += 20;
        } elseif ($intent === 'question') {
            $score += 15;
        } elseif ($intent === 'spam') {
            $score -= 40;
        }

        // Urgency
        $urgency = $context['urgency'] ?? 'low';
        if ($urgency === 'critical') {
            $score += 35;
        } elseif ($urgency === 'high') {
            $score += 20;
        } elseif ($urgency === 'medium') {
            $score += 10;
        }

        // Risk override
        $risk = $context['risk'] ?? 'none';
        if (in_array($risk, ['legal_risk', 'brand_risk', 'safety_risk'])) {
            $score += 30;
        }

        // Aging if unread
        if (! empty($context['unread'])) {
            $lastAt = isset($context['last_message_at']) ? Carbon::parse($context['last_message_at']) : null;
            if ($lastAt) {
                $hours = $lastAt->diffInHours(now());
                if ($hours >= 24) {
                    $score += 20;
                } elseif ($hours >= 4) {
                    $score += 10;
                }
            }
        }

        return (int) max(0, min(100, $score));
    }
}
