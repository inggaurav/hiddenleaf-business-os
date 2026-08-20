<?php

namespace HiddenLeaf\AgenticCrm\Services;

use HiddenLeaf\AgenticCrm\Domain\AgentTask;

final class RecheckScheduler
{
    public function create(AgentTask $source, \DateTimeImmutable $runAt, string $reason): AgentTask
    {
        return AgentTask::create(
            $source->organizationId,
            $source->workspaceId,
            $source->kind,
            $source->lane,
            $source->entityType,
            $source->entityId,
            array_merge($source->payload, ['recheck_reason' => $reason, 'parent_task_id' => $source->id]),
            hash('sha256', $source->id . '|' . $runAt->format(DATE_ATOM) . '|' . $reason),
            $runAt,
        );
    }
}
