<?php

namespace HiddenLeaf\AgenticCrm\Infrastructure;

use HiddenLeaf\AgenticCrm\Contracts\AgentStore;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Domain\ContactFact;
use HiddenLeaf\AgenticCrm\Domain\Evidence;

final class InMemoryAgentStore implements AgentStore
{
    /** @var array<string, AgentTask> */ public array $tasks = [];
    /** @var array<string, string> */ private array $taskStates = [];
    /** @var array<string, string> */ private array $leases = [];
    /** @var array<string, Evidence> */ public array $evidence = [];
    /** @var array<string, ContactFact> */ public array $facts = [];
    /** @var array<string, string> */ private array $idempotency = [];

    public function enqueue(AgentTask $task): AgentTask
    {
        $scopeKey = $task->organizationId . ':' . $task->workspaceId . ':' . $task->idempotencyKey;
        if (isset($this->idempotency[$scopeKey])) {
            return $this->tasks[$this->idempotency[$scopeKey]];
        }
        $this->tasks[$task->id] = $task;
        $this->taskStates[$task->id] = 'queued';
        $this->idempotency[$scopeKey] = $task->id;
        return $task;
    }

    public function claimDue(int $organizationId, int $workspaceId, string $workerId, int $limit = 1, int $leaseSeconds = 120): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $claimed = [];
        foreach ($this->tasks as $id => $task) {
            if (count($claimed) >= $limit) break;
            if ($task->organizationId !== $organizationId || $task->workspaceId !== $workspaceId) continue;
            if (($this->taskStates[$id] ?? null) !== 'queued' || $task->runAt > $now) continue;
            $this->taskStates[$id] = 'leased';
            $this->leases[$id] = $workerId;
            $claimed[] = $task;
        }
        return $claimed;
    }

    public function complete(string $taskId, string $workerId): void
    {
        if (($this->leases[$taskId] ?? null) !== $workerId) throw new \RuntimeException('Task lease mismatch.');
        $this->taskStates[$taskId] = 'completed';
        unset($this->leases[$taskId]);
    }

    public function fail(string $taskId, string $workerId, string $error, \DateTimeImmutable $retryAt): void
    {
        if (($this->leases[$taskId] ?? null) !== $workerId) throw new \RuntimeException('Task lease mismatch.');
        $old = $this->tasks[$taskId];
        $this->tasks[$taskId] = new AgentTask($old->id, $old->organizationId, $old->workspaceId, $old->kind, $old->lane, $old->entityType, $old->entityId, array_merge($old->payload, ['last_error' => $error]), $old->idempotencyKey, $retryAt, $old->attempt + 1);
        $this->taskStates[$taskId] = 'queued';
        unset($this->leases[$taskId]);
    }

    public function recordEvidence(Evidence $evidence): Evidence
    {
        $this->evidence[$evidence->id] = $evidence;
        return $evidence;
    }

    public function upsertFact(ContactFact $fact): ContactFact
    {
        if (in_array($fact->status, [ContactFact::AUTO_APPLIED, ContactFact::APPROVED], true)) {
            foreach ($this->facts as $id => $existing) {
                if ($existing->organizationId === $fact->organizationId && $existing->workspaceId === $fact->workspaceId && $existing->entityType === $fact->entityType && $existing->entityId === $fact->entityId && $existing->field === $fact->field && in_array($existing->status, [ContactFact::AUTO_APPLIED, ContactFact::APPROVED], true)) {
                    $this->facts[$id] = new ContactFact($existing->id, $existing->organizationId, $existing->workspaceId, $existing->entityType, $existing->entityId, $existing->field, $existing->value, $existing->evidenceId, $existing->score, ContactFact::SUPERSEDED, $existing->observedAt, $existing->reviewedBy, $existing->reviewedAt);
                }
            }
        }
        $this->facts[$fact->id] = $fact;
        return $fact;
    }

    public function pendingFacts(int $organizationId, int $workspaceId, int $limit = 50): array
    {
        return array_slice(array_values(array_filter($this->facts, fn (ContactFact $fact) => $fact->organizationId === $organizationId && $fact->workspaceId === $workspaceId && $fact->status === ContactFact::PENDING_REVIEW)), 0, $limit);
    }

    public function reviewFact(int $organizationId, int $workspaceId, string $factId, string $decision, int $reviewerId): ?ContactFact
    {
        $fact = $this->facts[$factId] ?? null;
        if (!$fact || $fact->organizationId !== $organizationId || $fact->workspaceId !== $workspaceId || $fact->status !== ContactFact::PENDING_REVIEW) return null;
        $status = $decision === 'approve' ? ContactFact::APPROVED : ContactFact::REJECTED;
        $reviewed = new ContactFact($fact->id, $fact->organizationId, $fact->workspaceId, $fact->entityType, $fact->entityId, $fact->field, $fact->value, $fact->evidenceId, $fact->score, $status, $fact->observedAt, $reviewerId, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        return $this->upsertFact($reviewed);
    }
}
