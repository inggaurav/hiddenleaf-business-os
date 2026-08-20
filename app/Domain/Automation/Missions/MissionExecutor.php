<?php

namespace App\Domain\Automation\Missions;

use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\RiskLevel;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Domain\MrFox\Validation\ToolInputValidator;
use App\Models\MrFoxMission;
use App\Models\MrFoxMissionStep;
use Illuminate\Support\Facades\DB;

class MissionExecutor
{
    public function __construct(
        private ?ActionApprovalService $approvalService = null,
        private ?ToolInputValidator $validator = null,
        private ?MissionStateMachine $stateMachine = null
    ) {
        $this->approvalService = $approvalService ?? app(ActionApprovalService::class);
        $this->validator = $validator ?? app(ToolInputValidator::class);
        $this->stateMachine = $stateMachine ?? app(MissionStateMachine::class);
    }

    /**
     * Advance the mission by executing its next pending step.
     *
     * @return array{status: string, step_executed: ?int, tool: ?string, observation: ?string, requires_approval: bool}
     */
    public function runNextStep(ToolContext $context, MrFoxMission $mission): array
    {
        $toolRegistry = app(MrFoxToolRegistry::class);

        return DB::transaction(function () use ($context, $mission, $toolRegistry) {
            $lockedMission = MrFoxMission::where('id', $mission->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedMission->status, ['completed', 'failed', 'cancelled'], true)) {
                return [
                    'status' => $lockedMission->status,
                    'step_executed' => null,
                    'tool' => null,
                    'observation' => 'Mission is in a terminal state.',
                    'requires_approval' => false,
                ];
            }

            // Find next pending step
            $step = MrFoxMissionStep::where('mission_id', $lockedMission->id)
                ->where('status', 'pending')
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if (! $step) {
                $this->stateMachine->transition($lockedMission, 'completed', 'All mission steps completed successfully.');

                return [
                    'status' => 'completed',
                    'step_executed' => null,
                    'tool' => null,
                    'observation' => 'All mission steps completed.',
                    'requires_approval' => false,
                ];
            }

            if ($lockedMission->status !== 'running') {
                $this->stateMachine->transition($lockedMission, 'running');
            }

            $toolName = $step->tool_name;
            if (! $toolRegistry->has($toolName)) {
                $step->update([
                    'status' => 'failed',
                    'observation' => "Tool '{$toolName}' is not registered.",
                    'completed_at' => now(),
                ]);
                $this->stateMachine->transition($lockedMission, 'failed', "Tool '{$toolName}' not found.");

                return [
                    'status' => 'failed',
                    'step_executed' => $step->sequence,
                    'tool' => $toolName,
                    'observation' => "Tool '{$toolName}' not found.",
                    'requires_approval' => false,
                ];
            }

            $tool = $toolRegistry->get($toolName);
            $inputParams = $step->input_params ?: [];

            // Check tool risk level (Phase 21)
            if ($tool->riskLevel() === RiskLevel::HIGH) {
                $proposal = $this->approvalService->createProposal(
                    $context,
                    $toolName,
                    $inputParams,
                    "Mission {$lockedMission->name}: execute {$toolName}",
                    RiskLevel::HIGH
                );

                $step->update([
                    'status' => 'waiting_for_approval',
                    'approval_proposal_id' => $proposal->id,
                    'observation' => "Step paused waiting for approval proposal #{$proposal->id}.",
                ]);

                $this->stateMachine->transition($lockedMission, 'waiting_for_approval', "Waiting for approval on step {$step->sequence} ({$toolName}).");

                return [
                    'status' => 'waiting_for_approval',
                    'step_executed' => $step->sequence,
                    'tool' => $toolName,
                    'observation' => "Requires approval proposal #{$proposal->id}.",
                    'requires_approval' => true,
                ];
            }

            // Execute safe tool
            $step->update(['status' => 'running', 'started_at' => now()]);

            try {
                $valResult = $this->validator->validate($tool, $inputParams);
                if (! $valResult['valid']) {
                    $errStr = 'Validation failed: '.json_encode($valResult['errors']);
                    $step->update([
                        'status' => 'failed',
                        'observation' => $errStr,
                        'completed_at' => now(),
                    ]);
                    $this->stateMachine->transition($lockedMission, 'failed', $errStr);

                    return [
                        'status' => 'failed',
                        'step_executed' => $step->sequence,
                        'tool' => $toolName,
                        'observation' => $errStr,
                        'requires_approval' => false,
                    ];
                }

                $validatedInput = $valResult['sanitized'];
                $result = $tool->execute($context, $validatedInput);

                if ($result->success) {
                    $step->update([
                        'status' => 'completed',
                        'output_data' => $result->data,
                        'evidence' => $result->evidence,
                        'observation' => $result->summary,
                        'completed_at' => now(),
                    ]);

                    $lockedMission->update([
                        'current_step' => $step->sequence,
                        'progress_summary' => "Completed step {$step->sequence}: {$toolName}",
                    ]);

                    $remaining = MrFoxMissionStep::where('mission_id', $lockedMission->id)
                        ->where('status', 'pending')
                        ->count();

                    if ($remaining === 0) {
                        $this->stateMachine->transition($lockedMission, 'completed', 'All steps executed successfully.');
                    }

                    return [
                        'status' => $remaining === 0 ? 'completed' : 'running',
                        'step_executed' => $step->sequence,
                        'tool' => $toolName,
                        'observation' => $result->summary,
                        'requires_approval' => false,
                    ];
                } else {
                    $step->update([
                        'status' => 'failed',
                        'observation' => $result->error,
                        'completed_at' => now(),
                    ]);
                    $this->stateMachine->transition($lockedMission, 'failed', "Failed at step {$step->sequence}: {$result->error}");

                    return [
                        'status' => 'failed',
                        'step_executed' => $step->sequence,
                        'tool' => $toolName,
                        'observation' => $result->error,
                        'requires_approval' => false,
                    ];
                }
            } catch (\Throwable $e) {
                $step->update([
                    'status' => 'failed',
                    'observation' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
                $this->stateMachine->transition($lockedMission, 'failed', "Exception at step {$step->sequence}: {$e->getMessage()}");

                return [
                    'status' => 'failed',
                    'step_executed' => $step->sequence,
                    'tool' => $toolName,
                    'observation' => $e->getMessage(),
                    'requires_approval' => false,
                ];
            }
        });
    }
}
