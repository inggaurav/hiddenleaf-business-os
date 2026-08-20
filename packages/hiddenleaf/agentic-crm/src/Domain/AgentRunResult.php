<?php

namespace HiddenLeaf\AgenticCrm\Domain;

final class AgentRunResult
{
    /** @param Finding[] $findings */
    public function __construct(
        public readonly array $findings = [],
        public readonly ?\DateTimeImmutable $recheckAt = null,
        public readonly ?string $recheckReason = null,
        public readonly array $metadata = [],
    ) {
    }
}
