<?php

namespace App\Http\Controllers\Automation;

use App\Domain\Automation\Missions\MissionExecutor;
use App\Domain\Automation\Missions\MissionPlanner;
use App\Domain\Automation\Missions\MissionStateMachine;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Http\Controllers\Controller;
use App\Models\MrFoxMission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MrFoxMissionController extends Controller
{
    public function __construct(
        private MissionPlanner $planner,
        private MissionExecutor $executor,
        private MissionStateMachine $stateMachine,
        private BusinessContextService $contextService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $missions = MrFoxMission::where('workspace_id', $wsId)->with(['user', 'steps'])->latest('id')->paginate(15);

        return Inertia::render('Missions/Index', [
            'missions' => $missions,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $mission = MrFoxMission::where('workspace_id', $wsId)
            ->where('id', $id)
            ->with(['steps', 'brandProfile', 'user'])
            ->firstOrFail();

        return response()->json($mission);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'objective' => 'required|string|max:5000',
            'brand_profile_id' => 'nullable|integer',
            'allowed_tools' => 'nullable|array',
        ]);

        $user = $request->user();
        $wsId = $user->current_workspace_id;
        $orgId = $user->currentWorkspace?->organization_id;

        $mission = MrFoxMission::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'user_id' => $user->id,
            'brand_profile_id' => $validated['brand_profile_id'] ?? null,
            'name' => $validated['name'],
            'objective' => $validated['objective'],
            'allowed_tools' => $validated['allowed_tools'] ?? null,
            'status' => 'draft',
        ]);

        $context = $this->contextService->createToolContext($user, $user->currentWorkspace);
        $plan = $this->planner->plan($context, $mission);

        return response()->json([
            'success' => true,
            'mission' => $mission->fresh(['steps']),
            'plan_summary' => $plan['plan_summary'],
        ]);
    }

    public function executeStep(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $mission = MrFoxMission::where('workspace_id', $wsId)->where('id', $id)->firstOrFail();
        $context = $this->contextService->createToolContext($user, $user->currentWorkspace);

        $result = $this->executor->runNextStep($context, $mission);

        return response()->json([
            'success' => true,
            'execution_result' => $result,
            'mission' => $mission->fresh(['steps']),
        ]);
    }

    public function control(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:pause,resume,cancel',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $mission = MrFoxMission::where('workspace_id', $wsId)->where('id', $id)->firstOrFail();

        $target = match ($validated['action']) {
            'pause' => 'paused',
            'resume' => 'running',
            'cancel' => 'cancelled',
        };

        $this->stateMachine->transition($mission, $target, $validated['reason'] ?? "User requested {$validated['action']}");

        return response()->json([
            'success' => true,
            'mission' => $mission->fresh(),
        ]);
    }
}
