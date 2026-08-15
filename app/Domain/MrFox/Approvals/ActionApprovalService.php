<?php

namespace App\Domain\MrFox\Approvals;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Models\MrFoxActionProposal;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActionApprovalService
{
    public function __construct(
        private MrFoxToolRegistry $registry,
        private PermissionService $permissionService
    ) {}

    public function createProposal(
        ToolContext $context,
        string $toolName,
        array $payload,
        string $humanSummary,
        RiskLevel $riskLevel
    ): MrFoxActionProposal {
        $canonicalPayload = $payload;
        ksort($canonicalPayload);
        $payloadHash = hash('sha256', json_encode($canonicalPayload));

        return MrFoxActionProposal::create([
            'organization_id' => $context->getOrganizationId(),
            'workspace_id' => $context->getWorkspaceId(),
            'user_id' => $context->user->id,
            'conversation_id' => $context->conversationId,
            'tool_name' => $toolName,
            'payload' => $canonicalPayload,
            'payload_hash' => $payloadHash,
            'human_summary' => $humanSummary,
            'risk_level' => $riskLevel->value,
            'status' => 'pending',
            'requested_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function approveAndExecute(int $proposalId, User $approver, ToolContext $context): ToolResult
    {
        return DB::transaction(function () use ($proposalId, $approver, $context) {
            /** @var MrFoxActionProposal|null $proposal */
            $proposal = MrFoxActionProposal::where('id', $proposalId)
                ->where('workspace_id', $context->getWorkspaceId())
                ->lockForUpdate()
                ->first();

            if (! $proposal) {
                return ToolResult::error('Proposal not found in this workspace.');
            }

            if ($proposal->status !== 'pending') {
                return ToolResult::error("Proposal cannot be executed. Current status: {$proposal->status}.");
            }

            // Expiration check
            if ($proposal->expires_at && $proposal->expires_at->isPast()) {
                $proposal->update(['status' => 'expired']);

                return ToolResult::error('Action proposal has expired.');
            }

            // Payload integrity check
            $canonicalPayload = $proposal->payload ?? [];
            ksort($canonicalPayload);
            $currentHash = hash('sha256', json_encode($canonicalPayload));

            if ($proposal->payload_hash && $proposal->payload_hash !== $currentHash) {
                $proposal->update([
                    'status' => 'failed',
                    'error' => 'Security violation: Proposal payload hash mismatch.',
                ]);

                return ToolResult::error('Proposal integrity check failed.');
            }

            $tool = $this->registry->get($proposal->tool_name);
            if (! $tool) {
                $proposal->update([
                    'status' => 'failed',
                    'error' => "Registered tool '{$proposal->tool_name}' is no longer available.",
                ]);

                return ToolResult::error("Tool '{$proposal->tool_name}' not available.");
            }

            // Execution-time RBAC re-verification
            $reqPermission = $tool->requiredPermission();
            $workspace = $context->workspace;
            $isSuperAdmin = method_exists($approver, 'isSuperAdmin') ? $approver->isSuperAdmin() : ($approver->role === 'super_admin');

            if (! $isSuperAdmin && $reqPermission !== null && $workspace !== null) {
                if (! $this->permissionService->allows($approver, $workspace, $reqPermission)) {
                    $proposal->update([
                        'status' => 'failed',
                        'error' => "Approver lacks required execution permission '{$reqPermission}'.",
                    ]);

                    return ToolResult::error("Approver lacks required permission '{$reqPermission}'.");
                }
            }

            // Mark approved immediately before executing
            $proposal->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ]);

            try {
                // Execute using the exact immutable stored payload
                $result = $tool->execute($context, $proposal->payload);

                if ($result->success) {
                    $proposal->update([
                        'status' => 'executed',
                        'executed_at' => now(),
                        'result' => $result->toArray(),
                    ]);
                } else {
                    $proposal->update([
                        'status' => 'failed',
                        'error' => $result->error,
                    ]);
                }

                return $result;
            } catch (\Throwable $e) {
                Log::error("MrFox action proposal execution failed for #{$proposalId}", ['error' => $e->getMessage()]);

                $proposal->update([
                    'status' => 'failed',
                    'error' => 'Execution failed due to server error.',
                    'error_trace' => $e->getMessage(),
                ]);

                return ToolResult::error('Execution failed: ' . $e->getMessage());
            }
        });
    }

    public function reject(int $proposalId, User $rejector, ?int $workspaceId): bool
    {
        return DB::transaction(function () use ($proposalId, $rejector, $workspaceId) {
            /** @var MrFoxActionProposal|null $proposal */
            $proposal = MrFoxActionProposal::where('id', $proposalId)
                ->where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->first();

            if (! $proposal || $proposal->status !== 'pending') {
                return false;
            }

            $proposal->update([
                'status' => 'rejected',
                'approved_at' => now(),
                'approved_by' => $rejector->id,
            ]);

            return true;
        });
    }
}
