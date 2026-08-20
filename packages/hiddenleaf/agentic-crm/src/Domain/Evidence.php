<?php

namespace HiddenLeaf\AgenticCrm\Domain;

use HiddenLeaf\AgenticCrm\Support\Uuid;

final class Evidence
{
    public function __construct(
        public readonly string $id,
        public readonly int $organizationId,
        public readonly int $workspaceId,
        public readonly string $taskId,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $field,
        public readonly mixed $value,
        public readonly string $sourceType,
        public readonly string $method,
        public readonly ?string $sourceUrl,
        public readonly int $score,
        public readonly string $band,
        public readonly \DateTimeImmutable $observedAt,
        public readonly array $metadata = [],
    ) {
    }

    public static function fromFinding(AgentTask $task, Finding $finding, int $score, string $band): self
    {
        return new self(
            Uuid::v4(),
            $task->organizationId,
            $task->workspaceId,
            $task->id,
            $task->entityType,
            $task->entityId,
            $finding->field,
            $finding->value,
            $finding->sourceType,
            $finding->method,
            $finding->sourceUrl,
            $score,
            $band,
            $finding->observedAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            $finding->metadata,
        );
    }
}
