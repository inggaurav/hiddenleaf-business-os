<?php

namespace HiddenLeaf\AgenticCrm\Contracts;

use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Domain\ContactFact;
use HiddenLeaf\AgenticCrm\Domain\Evidence;

interface AgentStore
{
    public function enqueue(AgentTask $task): AgentTask;

    /** @return AgentTask[] */
    public function claimDue(int $organizationId, int $workspaceId, string $workerId, int $limit = 1, int $leaseSeconds = 120): array;

    public function complete(string $taskId, string $workerId): void;

    public function fail(string $taskId, string $workerId, string $error, \DateTimeImmutable $retryAt): void;

    public function recordEvidence(Evidence $evidence): Evidence;

    public function upsertFact(ContactFact $fact): ContactFact;

    /** @return ContactFact[] */
    public function pendingFacts(int $organizationId, int $workspaceId, int $limit = 50): array;

    public function reviewFact(int $organizationId, int $workspaceId, string $factId, string $decision, int $reviewerId): ?ContactFact;
}
