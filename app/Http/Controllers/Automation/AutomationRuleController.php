<?php

namespace App\Http\Controllers\Automation;

use App\Domain\Automation\Actions\ActionRegistry;
use App\Domain\Automation\Execution\AutomationEngine;
use App\Domain\Automation\Triggers\TriggerRegistry;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function __construct(
        private TriggerRegistry $triggerRegistry,
        private ActionRegistry $actionRegistry,
        private AutomationEngine $engine
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $rules = AutomationRule::where('workspace_id', $wsId)->latest('id')->paginate(20);
        $recentRuns = AutomationRun::where('workspace_id', $wsId)->with('rule')->latest('id')->take(15)->get();

        return Inertia::render('Automation/Index', [
            'rules' => $rules,
            'recentRuns' => $recentRuns,
            'triggers' => array_values($this->triggerRegistry->all()),
            'actions' => array_values($this->actionRegistry->all()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'trigger_type' => 'required|string',
            'trigger_config' => 'nullable|array',
            'condition_config' => 'nullable|array',
            'action_config' => 'required|array|min:1',
            'enabled' => 'boolean',
        ]);

        $user = $request->user();
        $wsId = $user->current_workspace_id;
        $orgId = $user->currentWorkspace?->organization_id;

        $rule = AutomationRule::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trigger_type' => $validated['trigger_type'],
            'trigger_config' => $validated['trigger_config'] ?? null,
            'condition_config' => $validated['condition_config'] ?? null,
            'action_config' => $validated['action_config'],
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $rule = AutomationRule::where('workspace_id', $wsId)->where('id', $id)->firstOrFail();
        $rule->update(['enabled' => ! $rule->enabled]);

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function getRuns(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $runs = AutomationRun::where('workspace_id', $wsId)
            ->where('rule_id', $id)
            ->with('steps')
            ->latest('id')
            ->paginate(15);

        return response()->json($runs);
    }
}
