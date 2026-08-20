<?php

namespace HiddenLeaf\AgenticCrm\Contracts;

use HiddenLeaf\AgenticCrm\Domain\AgentDefinition;

interface AgentDefinitionStore
{
    public function save(int $organizationId, int $workspaceId, int $createdBy, AgentDefinition $definition): string;

    /** @return AgentDefinition[] */
    public function all(int $organizationId, int $workspaceId): array;
}
