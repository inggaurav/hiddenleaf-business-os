<?php

namespace HiddenLeaf\AgenticCrm\Services;

use HiddenLeaf\AgenticCrm\Contracts\AgentStore;
use HiddenLeaf\AgenticCrm\Contracts\TaskExecutor;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Domain\ContactFact;
use HiddenLeaf\AgenticCrm\Domain\Evidence;

final class AgentOrchestrator
{
    public function __construct(
        private readonly AgentStore $store,
        private readonly TaskExecutor $directExecutor,
        private readonly TaskExecutor $researchExecutor,
        private readonly EvidenceScorer $scorer = new EvidenceScorer(),
        private readonly FactPolicy $policy = new FactPolicy(),
        private readonly RecheckScheduler $rechecks = new RecheckScheduler(),
    ) {
    }

    public function enqueue(AgentTask $task): AgentTask
    {
        return $this->store->enqueue($task);
    }

    public function runOne(int $organizationId, int $workspaceId, string $workerId): bool
    {
        $tasks = $this->store->claimDue($organizationId, $workspaceId, $workerId, 1);
        if ($tasks === []) {
            return false;
        }

        $task = $tasks[0];
        try {
            $executor = $task->lane === AgentTask::LANE_DIRECT ? $this->directExecutor : $this->researchExecutor;
            $result = $executor->execute($task);

            foreach ($result->findings as $finding) {
                $score = $this->scorer->score($finding);
                $evidence = Evidence::fromFinding($task, $finding, $score, $this->scorer->band($score));
                $evidence = $this->store->recordEvidence($evidence);
                $this->store->upsertFact(ContactFact::fromEvidence($evidence, $this->policy->factStatus($evidence)));
            }

            if ($result->recheckAt && $result->recheckReason) {
                $this->store->enqueue($this->rechecks->create($task, $result->recheckAt, $result->recheckReason));
            }

            $this->store->complete($task->id, $workerId);
            return true;
        } catch (\Throwable $e) {
            $minutes = min(1440, 2 ** min(10, $task->attempt + 1));
            $this->store->fail($task->id, $workerId, substr($e->getMessage(), 0, 2000), (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify("+{$minutes} minutes"));
            return false;
        }
    }
}
