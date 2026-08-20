<?php

namespace HiddenLeaf\AgenticCrm\Domain;

use HiddenLeaf\AgenticCrm\Support\Uuid;

final class ContactFact
{
    public const AUTO_APPLIED = 'auto_applied';
    public const PENDING_REVIEW = 'pending_review';
    public const REJECTED = 'rejected';
    public const APPROVED = 'approved';
    public const SUPERSEDED = 'superseded';

    public function __construct(
        public readonly string $id,
        public readonly int $organizationId,
        public readonly int $workspaceId,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $field,
        public readonly mixed $value,
        public readonly string $evidenceId,
        public readonly int $score,
        public readonly string $status,
        public readonly \DateTimeImmutable $observedAt,
        public readonly ?int $reviewedBy = null,
        public readonly ?\DateTimeImmutable $reviewedAt = null,
    ) {
    }

    public static function fromEvidence(Evidence $evidence, string $status): self
    {
        return new self(
            Uuid::v4(),
            $evidence->organizationId,
            $evidence->workspaceId,
            $evidence->entityType,
            $evidence->entityId,
            $evidence->field,
            $evidence->value,
            $evidence->id,
            $evidence->score,
            $status,
            $evidence->observedAt,
        );
    }
}
