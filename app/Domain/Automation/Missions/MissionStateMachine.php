<?php

namespace App\Domain\Automation\Missions;

use App\Models\MrFoxMission;
use InvalidArgumentException;

class MissionStateMachine
{
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['queued', 'running', 'cancelled'],
        'queued' => ['running', 'paused', 'cancelled', 'failed'],
        'running' => ['paused', 'waiting_for_approval', 'completed', 'failed', 'cancelled'],
        'waiting_for_approval' => ['running', 'paused', 'failed', 'cancelled'],
        'paused' => ['running', 'cancelled'],
        'completed' => [],
        'failed' => ['queued'],
        'cancelled' => [],
    ];

    public function transition(MrFoxMission $mission, string $toStatus, ?string $summary = null): MrFoxMission
    {
        $current = $mission->status;
        if ($current === $toStatus) {
            return $mission;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];
        if (! in_array($toStatus, $allowed, true)) {
            throw new InvalidArgumentException("Mission cannot transition from '{$current}' to '{$toStatus}'.");
        }

        $mission->status = $toStatus;
        $mission->lock_version = (int) $mission->lock_version + 1;

        if ($toStatus === 'running' && ! $mission->started_at) {
            $mission->started_at = now();
        }
        if ($toStatus === 'paused') {
            $mission->paused_at = now();
        }
        if (in_array($toStatus, ['completed', 'failed', 'cancelled'], true)) {
            $mission->completed_at = now();
        }
        if ($summary) {
            $mission->progress_summary = $summary;
        }

        $mission->save();

        return $mission;
    }
}
