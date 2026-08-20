<?php

namespace HiddenLeaf\AgenticCrm\Domain;

final class Finding
{
    public function __construct(
        public readonly string $field,
        public readonly mixed $value,
        public readonly string $sourceType,
        public readonly string $method,
        public readonly ?string $sourceUrl = null,
        public readonly ?\DateTimeImmutable $observedAt = null,
        public readonly int $corroborationCount = 1,
        public readonly bool $conflicted = false,
        public readonly array $metadata = [],
    ) {
    }
}
