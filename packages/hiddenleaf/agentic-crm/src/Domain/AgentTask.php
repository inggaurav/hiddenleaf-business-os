<?php

namespace HiddenLeaf\AgenticCrm\Domain;

use HiddenLeaf\AgenticCrm\Support\Uuid;

final class AgentTask
{
    public const LANE_DIRECT = 'direct';
    public const LANE_RESEARCH = 'research';

    public function __construct(
        public readonly string $id,
        public readonly int $organizationId,
        public readonly int $workspaceId,
        public readonly string $kind,
        public readonly string $lane,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly array $payload,
        public readonly string $idempotencyKey,
        public readonly \DateTimeImmutable $runAt,
        public readonly int $attempt = 0,
    ) {
        if (!in_array($lane, [self::LANE_DIRECT, self::LANE_RESEARCH], true)) {
            throw new \InvalidArgumentException('Invalid agent work lane.');
        }
    }

    public static function create(
        int $organizationId,
        int $workspaceId,
        string $kind,
        string $lane,
        string $entityType,
        string $entityId,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?\DateTimeImmutable $runAt = null,
    ): self {
        return new self(
            Uuid::v4(),
            $organizationId,
            $workspaceId,
            $kind,
            $lane,
            $entityType,
            $entityId,
            $payload,
            $idempotencyKey ?? hash('sha256', implode('|', [$organizationId, $workspaceId, $kind, $entityType, $entityId, json_encode($payload)])),
            $runAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }
}
