<?php

namespace HiddenLeaf\AgenticCrm\Infrastructure;

use HiddenLeaf\AgenticCrm\Contracts\AgentDefinitionStore;
use HiddenLeaf\AgenticCrm\Domain\AgentDefinition;
use HiddenLeaf\AgenticCrm\Support\Uuid;

final class PostgresAgentDefinitionStore implements AgentDefinitionStore
{
    public function __construct(private readonly \PDO $pdo) {}

    public function save(int $organizationId, int $workspaceId, int $createdBy, AgentDefinition $definition): string
    {
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare("INSERT INTO agent_definitions (id,organization_id,workspace_id,name,instructions,allowed_tools,allowed_resources,allowed_hosts,version_hash,created_by) VALUES (:id,:org,:ws,:name,:instructions,CAST(:tools AS jsonb),CAST(:resources AS jsonb),CAST(:hosts AS jsonb),:hash,:created_by) ON CONFLICT (organization_id,workspace_id,name,version_hash) DO UPDATE SET enabled=true,updated_at=NOW() RETURNING id");
        $stmt->execute([
            'id'=>$id,'org'=>$organizationId,'ws'=>$workspaceId,'name'=>$definition->name,'instructions'=>$definition->instructions,
            'tools'=>json_encode($definition->allowedTools, JSON_THROW_ON_ERROR),'resources'=>json_encode($definition->allowedResources, JSON_THROW_ON_ERROR),
            'hosts'=>json_encode($definition->allowedHosts, JSON_THROW_ON_ERROR),'hash'=>$definition->versionHash,'created_by'=>$createdBy,
        ]);
        return (string) $stmt->fetchColumn();
    }

    public function all(int $organizationId, int $workspaceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM agent_definitions WHERE organization_id=:org AND workspace_id=:ws AND enabled=true ORDER BY name,created_at DESC');
        $stmt->execute(['org'=>$organizationId,'ws'=>$workspaceId]);
        return array_map(fn (array $row) => new AgentDefinition(
            $row['name'], $row['instructions'], json_decode($row['allowed_tools'], true) ?: [], json_decode($row['allowed_resources'], true) ?: [], json_decode($row['allowed_hosts'], true) ?: [], $row['version_hash']
        ), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
