<?php

namespace HiddenLeaf\Http\Controllers\Api\V1;

use HiddenLeaf\AgenticCrm\Contracts\AgentStore;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Services\AgentOrchestrator;
use HiddenLeaf\Architecture\ActorContext;
use HiddenLeaf\Architecture\OrganizationContext;
use HiddenLeaf\Architecture\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AgentIntelligenceController
{
    public function __construct(
        private readonly AgentOrchestrator $orchestrator,
        private readonly AgentStore $store,
        private readonly OrganizationContext $organization,
        private readonly WorkspaceContext $workspace,
        private readonly ActorContext $actor,
    ) {
    }

    public function enqueueResearch(Request $request): JsonResponse
    {
        $this->requirePermission('crm.intelligence.run');
        $validated = $request->validate([
            'entity_type' => 'required|string|in:contact,company,deal',
            'entity_id' => 'required|string|max:120',
            'kind' => 'nullable|string|max:120',
            'payload' => 'nullable|array',
        ]);
        $task = AgentTask::create(
            $this->organizationId(), $this->workspaceId(), $validated['kind'] ?? 'entity.research', AgentTask::LANE_RESEARCH,
            $validated['entity_type'], $validated['entity_id'], $validated['payload'] ?? []
        );
        $task = $this->orchestrator->enqueue($task);
        return response()->json(['task_id'=>$task->id,'status'=>'queued','run_at'=>$task->runAt->format(DATE_ATOM)], 202);
    }

    public function pendingReviews(): JsonResponse
    {
        $this->requirePermission('crm.intelligence.review');
        $facts = $this->store->pendingFacts($this->organizationId(), $this->workspaceId());
        return response()->json(['data'=>array_map(fn ($fact) => [
            'id'=>$fact->id,'entity_type'=>$fact->entityType,'entity_id'=>$fact->entityId,'field'=>$fact->field,
            'value'=>$fact->value,'score'=>$fact->score,'status'=>$fact->status,'observed_at'=>$fact->observedAt->format(DATE_ATOM)
        ], $facts)]);
    }

    public function review(Request $request, string $factId): JsonResponse
    {
        $this->requirePermission('crm.intelligence.review');
        $validated = $request->validate(['decision'=>'required|string|in:approve,reject']);
        $fact = $this->store->reviewFact($this->organizationId(), $this->workspaceId(), $factId, $validated['decision'], $this->actor->getUserId() ?? 0);
        if (!$fact) return response()->json(['message'=>'Pending fact not found in this workspace.'], 404);
        return response()->json(['id'=>$fact->id,'status'=>$fact->status]);
    }

    private function requirePermission(string $permission): void
    {
        if (!$this->actor->getUserId()) abort(401);
        if (!$this->actor->hasPermission($permission)) abort(403);
    }

    private function organizationId(): int
    {
        $id = $this->organization->getId();
        if (!$id) throw new \RuntimeException('Organization context is required.');
        return $id;
    }

    private function workspaceId(): int
    {
        $id = $this->workspace->getId();
        if (!$id || $this->workspace->getOrganizationId() !== $this->organizationId()) throw new \RuntimeException('Valid workspace context is required.');
        return $id;
    }
}
