<?php

namespace HiddenLeaf\AgenticCrm\Services;

use HiddenLeaf\AgenticCrm\Domain\Finding;

final class EvidenceScorer
{
    private const BASE = [
        'first_party_signed_email' => 95,
        'first_party_crm_history' => 92,
        'first_party_calendar' => 90,
        'official_company_site' => 85,
        'verified_provider' => 80,
        'reputable_public_source' => 70,
        'social_profile' => 60,
        'generic_web' => 50,
        'model_inference' => 20,
    ];

    public function score(Finding $finding, ?\DateTimeImmutable $now = null): int
    {
        $score = self::BASE[$finding->sourceType] ?? 35;
        $score += min(10, max(0, $finding->corroborationCount - 1) * 5);

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($finding->observedAt && $finding->observedAt >= $now->modify('-30 days')) {
            $score += 5;
        }
        if ($finding->conflicted) {
            $score -= 25;
        }

        return max(0, min(100, $score));
    }

    public function band(int $score): string
    {
        return $score >= 80 ? 'strong' : ($score >= 55 ? 'review' : 'weak');
    }
}
