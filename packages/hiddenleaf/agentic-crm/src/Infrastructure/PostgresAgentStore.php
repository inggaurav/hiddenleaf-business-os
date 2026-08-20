<?php

namespace HiddenLeaf\AgenticCrm\Infrastructure;

use HiddenLeaf\AgenticCrm\Contracts\AgentStore;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Domain\ContactFact;
use HiddenLeaf\AgenticCrm\Domain\Evidence;

final class PostgresAgentStore implements AgentStore
{
    public function __construct(private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function enqueue(AgentTask $task): AgentTask
    {
        $sql = 'INSERT INTO agent_tasks (id, organization_id, workspace_id, kind, lane, entity_type, entity_id, payload, idempotency_key, run_at) VALUES (:id,:org,:ws,:kind,:lane,:etype,:eid,CAST(:payload AS jsonb),:idem,:run_at) ON CONFLICT (organization_id, workspace_id, idempotency_key) DO UPDATE SET idempotency_key = EXCLUDED.idempotency_key RETURNING *';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->taskParams($task));
        return $this->hydrateTask($stmt->fetch(\PDO::FETCH_ASSOC));
    }

    public function claimDue(int $organizationId, int $workspaceId, string $workerId, int $limit = 1, int $leaseSeconds = 120): array
    {
        $limit = max(1, min(100, $limit));
        $leaseSeconds = max(30, min(3600, $leaseSeconds));
        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare("SELECT * FROM agent_tasks WHERE organization_id=:org AND workspace_id=:ws AND run_at<=NOW() AND (status='queued' OR (status='leased' AND lease_until<NOW())) ORDER BY run_at,id FOR UPDATE SKIP LOCKED LIMIT {$limit}");
            $select->execute(['org' => $organizationId, 'ws' => $workspaceId]);
            $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
            $update = $this->pdo->prepare("UPDATE agent_tasks SET status='leased', leased_by=:worker, lease_until=NOW() + (:lease || ' seconds')::interval, updated_at=NOW() WHERE id=:id");
            foreach ($rows as $row) {
                $update->execute(['worker' => $workerId, 'lease' => (string) $leaseSeconds, 'id' => $row['id']]);
            }
            $this->pdo->commit();
            return array_map(fn (array $row) => $this->hydrateTask(array_merge($row, ['status' => 'leased'])), $rows);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function complete(string $taskId, string $workerId): void
    {
        $stmt = $this->pdo->prepare("UPDATE agent_tasks SET status='completed', leased_by=NULL, lease_until=NULL, completed_at=NOW(), updated_at=NOW() WHERE id=:id AND status='leased' AND leased_by=:worker");
        $stmt->execute(['id' => $taskId, 'worker' => $workerId]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('Task lease mismatch or task no longer leased.');
    }

    public function fail(string $taskId, string $workerId, string $error, \DateTimeImmutable $retryAt): void
    {
        $stmt = $this->pdo->prepare("UPDATE agent_tasks SET status='queued', attempt=attempt+1, last_error=:error, run_at=:run_at, leased_by=NULL, lease_until=NULL, updated_at=NOW() WHERE id=:id AND status='leased' AND leased_by=:worker");
        $stmt->execute(['error' => $error, 'run_at' => $retryAt->format('Y-m-d H:i:sP'), 'id' => $taskId, 'worker' => $workerId]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('Task lease mismatch or task no longer leased.');
    }

    public function recordEvidence(Evidence $evidence): Evidence
    {
        $stmt = $this->pdo->prepare('INSERT INTO agent_evidence (id,organization_id,workspace_id,task_id,entity_type,entity_id,field,value,source_type,method,source_url,score,band,observed_at,metadata) VALUES (:id,:org,:ws,:task,:etype,:eid,:field,CAST(:value AS jsonb),:source,:method,:url,:score,:band,:observed,CAST(:metadata AS jsonb)) ON CONFLICT (id) DO NOTHING');
        $stmt->execute(['id'=>$evidence->id,'org'=>$evidence->organizationId,'ws'=>$evidence->workspaceId,'task'=>$evidence->taskId,'etype'=>$evidence->entityType,'eid'=>$evidence->entityId,'field'=>$evidence->field,'value'=>json_encode($evidence->value, JSON_THROW_ON_ERROR),'source'=>$evidence->sourceType,'method'=>$evidence->method,'url'=>$evidence->sourceUrl,'score'=>$evidence->score,'band'=>$evidence->band,'observed'=>$evidence->observedAt->format('Y-m-d H:i:sP'),'metadata'=>json_encode($evidence->metadata, JSON_THROW_ON_ERROR)]);
        return $evidence;
    }

    public function upsertFact(ContactFact $fact): ContactFact
    {
        $this->pdo->beginTransaction();
        try {
            if (in_array($fact->status, [ContactFact::AUTO_APPLIED, ContactFact::APPROVED], true)) {
                $supersede = $this->pdo->prepare("UPDATE contact_facts SET status='superseded', superseded_at=NOW() WHERE organization_id=:org AND workspace_id=:ws AND entity_type=:etype AND entity_id=:eid AND field=:field AND status IN ('auto_applied','approved') AND id<>:id");
                $supersede->execute(['org'=>$fact->organizationId,'ws'=>$fact->workspaceId,'etype'=>$fact->entityType,'eid'=>$fact->entityId,'field'=>$fact->field,'id'=>$fact->id]);
            }
            $stmt = $this->pdo->prepare('INSERT INTO contact_facts (id,organization_id,workspace_id,entity_type,entity_id,field,value,evidence_id,score,status,observed_at,reviewed_by,reviewed_at) VALUES (:id,:org,:ws,:etype,:eid,:field,CAST(:value AS jsonb),:evidence,:score,:status,:observed,:reviewed_by,:reviewed_at) ON CONFLICT (id) DO UPDATE SET value=EXCLUDED.value,evidence_id=EXCLUDED.evidence_id,score=EXCLUDED.score,status=EXCLUDED.status,reviewed_by=EXCLUDED.reviewed_by,reviewed_at=EXCLUDED.reviewed_at RETURNING *');
            $stmt->execute($this->factParams($fact));
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->pdo->commit();
            return $this->hydrateFact($row);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function pendingFacts(int $organizationId, int $workspaceId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare("SELECT * FROM contact_facts WHERE organization_id=:org AND workspace_id=:ws AND status='pending_review' ORDER BY score DESC, observed_at DESC LIMIT {$limit}");
        $stmt->execute(['org'=>$organizationId,'ws'=>$workspaceId]);
        return array_map(fn (array $row) => $this->hydrateFact($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function reviewFact(int $organizationId, int $workspaceId, string $factId, string $decision, int $reviewerId): ?ContactFact
    {
        if (!in_array($decision, ['approve','reject'], true)) throw new \InvalidArgumentException('Decision must be approve or reject.');
        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare("SELECT * FROM contact_facts WHERE id=:id AND organization_id=:org AND workspace_id=:ws AND status='pending_review' FOR UPDATE");
            $select->execute(['id'=>$factId,'org'=>$organizationId,'ws'=>$workspaceId]);
            $row = $select->fetch(\PDO::FETCH_ASSOC);
            if (!$row) { $this->pdo->rollBack(); return null; }
            if ($decision === 'approve') {
                $supersede = $this->pdo->prepare("UPDATE contact_facts SET status='superseded', superseded_at=NOW() WHERE organization_id=:org AND workspace_id=:ws AND entity_type=:etype AND entity_id=:eid AND field=:field AND status IN ('auto_applied','approved') AND id<>:id");
                $supersede->execute(['org'=>$organizationId,'ws'=>$workspaceId,'etype'=>$row['entity_type'],'eid'=>$row['entity_id'],'field'=>$row['field'],'id'=>$factId]);
            }
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $update = $this->pdo->prepare('UPDATE contact_facts SET status=:status,reviewed_by=:reviewer,reviewed_at=NOW() WHERE id=:id RETURNING *');
            $update->execute(['status'=>$status,'reviewer'=>$reviewerId,'id'=>$factId]);
            $updated = $update->fetch(\PDO::FETCH_ASSOC);
            $this->pdo->commit();
            return $this->hydrateFact($updated);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function taskParams(AgentTask $task): array
    {
        return ['id'=>$task->id,'org'=>$task->organizationId,'ws'=>$task->workspaceId,'kind'=>$task->kind,'lane'=>$task->lane,'etype'=>$task->entityType,'eid'=>$task->entityId,'payload'=>json_encode($task->payload, JSON_THROW_ON_ERROR),'idem'=>$task->idempotencyKey,'run_at'=>$task->runAt->format('Y-m-d H:i:sP')];
    }

    private function hydrateTask(array $row): AgentTask
    {
        return new AgentTask($row['id'],(int)$row['organization_id'],(int)$row['workspace_id'],$row['kind'],$row['lane'],$row['entity_type'],$row['entity_id'],json_decode($row['payload'] ?? '{}',true) ?: [],$row['idempotency_key'],new \DateTimeImmutable($row['run_at']), (int)($row['attempt'] ?? 0));
    }

    private function factParams(ContactFact $fact): array
    {
        return ['id'=>$fact->id,'org'=>$fact->organizationId,'ws'=>$fact->workspaceId,'etype'=>$fact->entityType,'eid'=>$fact->entityId,'field'=>$fact->field,'value'=>json_encode($fact->value, JSON_THROW_ON_ERROR),'evidence'=>$fact->evidenceId,'score'=>$fact->score,'status'=>$fact->status,'observed'=>$fact->observedAt->format('Y-m-d H:i:sP'),'reviewed_by'=>$fact->reviewedBy,'reviewed_at'=>$fact->reviewedAt?->format('Y-m-d H:i:sP')];
    }

    private function hydrateFact(array $row): ContactFact
    {
        return new ContactFact($row['id'],(int)$row['organization_id'],(int)$row['workspace_id'],$row['entity_type'],$row['entity_id'],$row['field'],json_decode($row['value'],true),$row['evidence_id'],(int)$row['score'],$row['status'],new \DateTimeImmutable($row['observed_at']),isset($row['reviewed_by']) ? (int)$row['reviewed_by'] : null,!empty($row['reviewed_at']) ? new \DateTimeImmutable($row['reviewed_at']) : null);
    }
}
