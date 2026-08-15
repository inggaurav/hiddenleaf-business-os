<?php

namespace App\Domain\MrFox\Approvals;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Models\MrFoxActionProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActionApprovalService
{
    public function __construct(private MrFoxToolRegistry $registry) {}

    public function createProposal(
        ToolContext $context,
        string $toolName,
        array $payload,
        string $humanSummary,
        RiskLevel $riskLevel
    ): MrFoxActionProposal {
        return MrFoxActionProposal::create([
            'organization_id' => $context->getOrganizationId(),
            'workspace_id' => $context->getWorkspaceId(),
            'user_id' => $context->user->id,
            'conversation_id' => $context->conversationId,
            'tool_name' => $toolName,
            'payload' => $payload,
            'human_summary' => $humanSummary,
            'risk_level' => $riskLevel->value,
            'status' => 'pending',
            'requested_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function approveAndExecute(int $proposalId, User $approver, ToolContext $context): ToolResult
    {
        $proposal = MrFoxActionProposal::where('id', $proposalId)
            ->where('workspace_id', $context->getWorkspaceId())
            ->where('status', 'pending')
            ->first();

        if (! $proposal) {
            return ToolResult::error('Proposal not found, already processed, or expired.');
        }

        if ($proposal->expires_at && $proposal->expires_at->isPast()) {
            $proposal->update(['status' => 'expired']);

            return ToolResult::error('Action proposal has expired.');
        }

        $tool = $this->registry->get($proposal->tool_name);
        if (! $tool) {
            $proposal->update([
                'status' => 'failed',
                'error' => "Registered tool '{$proposal->tool_name}' no longer available.",
            ]);

            return ToolResult::error("Tool '{$proposal->tool_name}' not available.");
        }

        return DB::transaction(function () use ($proposal, $tool, $context, $approver) {
            $proposal->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ]);

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
        });
    }

    public function reject(int $proposalId, User $rejector, ?int $workspaceId): bool
    {
        $proposal = MrFoxActionProposal::where('id', $proposalId)
            ->where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->first();

        if (! $proposal) {
            return false;
        }

        $proposal->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $rejector->id,
        ]);

        return true;
    }
}
