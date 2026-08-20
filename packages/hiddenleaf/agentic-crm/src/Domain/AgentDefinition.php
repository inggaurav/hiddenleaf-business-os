<?php

namespace HiddenLeaf\AgenticCrm\Domain;

final class AgentDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $instructions,
        public readonly array $allowedTools,
        public readonly array $allowedResources,
        public readonly array $allowedHosts,
        public readonly string $versionHash,
    ) {
    }
}
