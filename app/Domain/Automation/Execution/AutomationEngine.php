<?php

namespace App\Domain\Automation\Execution;

use App\Domain\Automation\Actions\ActionRegistry;
use App\Domain\Automation\Conditions\ConditionEvaluator;
use App\Domain\Automation\Triggers\TriggerRegistry;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Models\Workspace;
use App\Services\AddonManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationEngine
{
    public function __construct(
        private TriggerRegistry $triggerRegistry,
        private ActionRegistry $actionRegistry,
        private ConditionEvaluator $conditionEvaluator,
        private AddonManager $addons,
    ) {}

    /** @return array<AutomationRun> */
    public function dispatch(int $workspaceId, string $triggerEvent, array $payload, ?string $traceId = null, int $depth = 1): array
    {
        if ($depth > 5) {
            Log::warning("Automation loop detected and suppressed at depth {$depth} for event {$triggerEvent}");

            return [];
        }

        $workspace = Workspace::query()->with('organization')->find($workspaceId);
        if (! $workspace) {
            return [];
        }

        $triggerOwner = $this->triggerRegistry->addonOwner($triggerEvent);
        if ($triggerOwner !== null && ! $this->addons->canUse($workspace, $triggerOwner)) {
            return [];
        }

        $traceId = $traceId ?: uniqid('trace_auto_');
        $rules = AutomationRule::where('workspace_id', $workspaceId)
            ->where('enabled', true)
            ->where('trigger_type', $triggerEvent)
            ->get();

        $runs = [];
        foreach ($rules as $rule) {
            if (! $this->conditionEvaluator->evaluate($rule->condition_config, $payload)) {
                continue;
            }

            $entityId = $payload['id'] ?? $payload['lead_id'] ?? $payload['invoice_id'] ?? $payload['conversation_id'] ?? 'none';
            $payloadHash = substr(md5(json_encode($payload)), 0, 8);
            $idempotencyKey = "rule_{$rule->id}_{$triggerEvent}_{$entityId}_{$payloadHash}";

            $run = DB::transaction(function () use ($rule, $triggerEvent, $payload, $idempotencyKey, $traceId, $depth, $workspace) {
                $existing = AutomationRun::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }

                $run = AutomationRun::create([
                    'organization_id' => $rule->organization_id,
                    'workspace_id' => $rule->workspace_id,
                    'rule_id' => $rule->id,
                    'trigger_event' => $triggerEvent,
                    'trigger_payload' => $payload,
                    'status' => 'running',
                    'idempotency_key' => $idempotencyKey,
                    'trace_id' => $traceId,
                    'depth' => $depth,
                    'started_at' => now(),
                ]);

                $allCompleted = true;
                $hasApproval = false;

                foreach (($rule->action_config ?: []) as $idx => $actConfig) {
                    $actionName = $actConfig['action'] ?? $actConfig['name'] ?? '';
                    $actionInput = $actConfig['input'] ?? $actConfig['params'] ?? [];

                    foreach ($actionInput as $k => $v) {
                        if (is_string($v) && str_contains($v, '{{')) {
                            foreach ($payload as $pk => $pv) {
                                if (is_scalar($pv)) {
                                    $v = str_replace("{{{$pk}}}", (string) $pv, $v);
                                }
                            }
                            $actionInput[$k] = $v;
                        }
                    }

                    $actionOwner = $this->actionRegistry->addonOwner($actionName);
                    $unavailableAddonAction = $actionOwner !== null && ! $this->addons->canUse($workspace, $actionOwner);

                    if (! $this->actionRegistry->has($actionName) || $unavailableAddonAction) {
                        AutomationRunStep::create([
                            'run_id' => $run->id,
                            'step_index' => $idx,
                            'action_name' => $actionName,
                            'input_payload' => $actionInput,
                            'status' => 'failed',
                            'error_message' => $unavailableAddonAction
                                ? "Action '{$actionName}' belongs to a disabled or unentitled add-on."
                                : "Action '{$actionName}' not registered.",
                            'started_at' => now(),
                            'completed_at' => now(),
                        ]);
                        $allCompleted = false;
                        break;
                    }

                    $actionObj = $this->actionRegistry->get($actionName);
                    $step = AutomationRunStep::create([
                        'run_id' => $run->id,
                        'step_index' => $idx,
                        'action_name' => $actionName,
                        'input_payload' => $actionInput,
                        'status' => 'running',
                        'started_at' => now(),
                    ]);

                    try {
                        $res = $actionObj->execute($run, $actionInput);
                        if (! empty($res['requires_approval'])) {
                            $step->update(['status' => 'waiting_for_approval', 'approval_proposal_id' => $res['proposal_id'] ?? null, 'output_payload' => $res['data'] ?? [], 'completed_at' => now()]);
                            $hasApproval = true;
                        } elseif ($res['success']) {
                            $step->update(['status' => 'completed', 'output_payload' => $res['data'] ?? [], 'completed_at' => now()]);
                        } else {
                            $step->update(['status' => 'failed', 'error_message' => $res['error'] ?? 'Execution failed', 'completed_at' => now()]);
                            $allCompleted = false;
                            break;
                        }
                    } catch (\Throwable $e) {
                        $step->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
                        $allCompleted = false;
                        break;
                    }
                }

                $run->update(['status' => $hasApproval ? 'waiting_for_approval' : ($allCompleted ? 'completed' : 'failed'), 'completed_at' => now()]);
                $rule->increment('run_count');
                $rule->update(['last_run_at' => now()]);
                if (! $allCompleted) {
                    $rule->increment('failure_count');
                }

                return $run;
            });

            $runs[] = $run;
        }

        return $runs;
    }
}
