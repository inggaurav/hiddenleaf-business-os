<?php

namespace App\Http\Controllers\CommandCenter;

use App\Domain\CommandCenter\Activity\ExecutiveActivityTimelineService;
use App\Domain\CommandCenter\Anomalies\AnomalyDetectionEngine;
use App\Domain\CommandCenter\Briefings\ExecutiveBriefingService;
use App\Domain\CommandCenter\Health\BusinessHealthService;
use App\Domain\CommandCenter\Priorities\BusinessPriorityService;
use App\Domain\CommandCenter\Recommendations\ExecutiveRecommendationService;
use App\Domain\CommandCenter\Search\BusinessSearchService;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\MrFoxActionProposal;
use App\Models\MrFoxMission;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommandCenterController extends Controller
{
    public function __construct(
        private BusinessHealthService $healthService,
        private BusinessPriorityService $priorityService,
        private ExecutiveRecommendationService $recommendationService,
        private ExecutiveBriefingService $briefingService,
        private AnomalyDetectionEngine $anomalyEngine,
        private BusinessSearchService $searchService,
        private ExecutiveActivityTimelineService $timelineService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::find($wsId) ?? Workspace::first();

        if (! $workspace) {
            return Inertia::render('CommandCenter/Index', [
                'health' => null,
                'priorities' => [],
                'recommendations' => [],
                'briefing' => null,
                'anomalies' => [],
                'timeline' => [],
                'runningSystems' => [],
            ]);
        }

        $health = $this->healthService->evaluateHealth($user, $workspace);
        $priorities = array_slice($this->priorityService->getPriorities($user, $workspace), 0, 5);
        $recommendations = array_slice($this->recommendationService->getRecommendations($user, $workspace), 0, 5);
        $briefing = $this->briefingService->generateBriefing($user, $workspace, 'today');
        $anomalies = $this->anomalyEngine->detectAnomalies($user, $workspace);
        $timeline = $this->timelineService->getTimeline($user, $workspace, 15);

        $runningSystems = [
            'active_automations' => AutomationRule::where('workspace_id', $workspace->id)->where('enabled', true)->count(),
            'running_missions' => MrFoxMission::where('workspace_id', $workspace->id)->whereIn('status', ['running', 'queued'])->count(),
            'waiting_approvals' => MrFoxActionProposal::where('workspace_id', $workspace->id)->where('status', 'pending')->count(),
        ];

        return Inertia::render('CommandCenter/Index', [
            'health' => [
                'overall' => $health['overall']->toArray(),
                'dimensions' => array_map(fn ($d) => $d->toArray(), $health['dimensions']),
            ],
            'priorities' => array_map(fn ($p) => $p->toArray(), $priorities),
            'recommendations' => array_map(fn ($r) => $r->toArray(), $recommendations),
            'briefing' => $briefing->toArray(),
            'anomalies' => array_map(fn ($a) => $a->toArray(), $anomalies),
            'timeline' => array_map(fn ($t) => $t->toArray(), $timeline),
            'runningSystems' => $runningSystems,
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        $health = $this->healthService->evaluateHealth($user, $workspace);

        return response()->json([
            'overall' => $health['overall']->toArray(),
            'dimensions' => array_map(fn ($d) => $d->toArray(), $health['dimensions']),
        ]);
    }

    public function priorities(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        $priorities = $this->priorityService->getPriorities($user, $workspace);

        return response()->json(array_map(fn ($p) => $p->toArray(), $priorities));
    }

    public function briefing(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);
        $period = $request->query('period', 'today');

        $briefing = $this->briefingService->generateBriefing($user, $workspace, $period);

        return response()->json($briefing->toArray());
    }

    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);
        $limit = (int) $request->query('limit', 20);

        $timeline = $this->timelineService->getTimeline($user, $workspace, $limit);

        return response()->json(array_map(fn ($t) => $t->toArray(), $timeline));
    }

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);
        $query = (string) $request->query('q', '');

        $results = $this->searchService->search($user, $workspace, $query, 20);

        return response()->json(array_map(fn ($r) => $r->toArray(), $results));
    }
}
