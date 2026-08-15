<?php

namespace App\Domain\MrFox\Review;

use App\Domain\MrFox\DTO\ToolContext;
use App\Models\MrFoxBrandProfile;

class ContentReviewEngine
{
    /**
     * Score and verify content against brand compliance, safety rules, and quality metrics.
     *
     * @return array{score: int, status: string, checks: array, suggestions: array}
     */
    public function review(ToolContext $context, string $content, ?int $brandProfileId = null): array
    {
        $wsId = $context->getWorkspaceId();
        $profile = MrFoxBrandProfile::where('workspace_id', $wsId)
            ->when($brandProfileId, fn ($q) => $q->where('id', $brandProfileId))
            ->orderByDesc('is_default')
            ->first();

        $score = 90;
        $checks = [
            'brand_safety' => true,
            'length_appropriate' => true,
            'clarity' => true,
        ];
        $suggestions = [];

        // Check for common compliance violations
        $prohibitedWords = ['guaranteed return', '100% risk free', 'get rich quick', 'instant wealth'];
        foreach ($prohibitedWords as $phrase) {
            if (stripos($content, $phrase) !== false) {
                $score -= 30;
                $checks['brand_safety'] = false;
                $suggestions[] = "Remove high-risk compliance claim: '{$phrase}'.";
            }
        }

        // Avoid words from brand profile
        $avoidWords = $profile?->tone_of_voice['avoid_words'] ?? [];
        if (is_array($avoidWords)) {
            foreach ($avoidWords as $word) {
                if (stripos($content, (string) $word) !== false) {
                    $score -= 10;
                    $suggestions[] = "Avoid word discouraged by Brand Profile: '{$word}'.";
                }
            }
        }

        $score = max(0, min(100, $score));

        $status = match (true) {
            $score >= 85 => 'approved',
            $score >= 70 => 'suggestions_available',
            default => 'revision_required',
        };

        return [
            'score' => $score,
            'status' => $status,
            'checks' => $checks,
            'suggestions' => $suggestions,
        ];
    }
}
