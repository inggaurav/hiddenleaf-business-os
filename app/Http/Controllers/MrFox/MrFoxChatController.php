<?php

namespace App\Http\Controllers\MrFox;

use App\Domain\MrFox\Agent\MrFoxAgent;
use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Insights\BusinessInsightService;
use App\Http\Controllers\Controller;
use App\Models\AssistantMessage;
use App\Models\AssistantSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MrFoxChatController extends Controller
{
    public function __construct(
        private MrFoxAgent $agent,
        private BusinessInsightService $insightService,
        private ActionApprovalService $approvalService,
        private BusinessContextService $contextService
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'session_id' => 'nullable|integer',
            'conversation_id' => 'nullable|string',
            'active_page' => 'nullable|string',
        ]);

        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();
        $activePage = $validated['active_page'] ?? 'Dashboard';

        // Find or create session
        $session = null;
        if (! empty($validated['session_id'])) {
            $session = AssistantSession::where('id', $validated['session_id'])
                ->where('user_id', $user->id)
                ->where('workspace_id', $workspace?->id)
                ->first();
        }

        if (! $session) {
            $session = AssistantSession::create([
                'title' => substr($validated['message'], 0, 40) . '...',
                'user_id' => $user->id,
                'workspace_id' => $workspace?->id,
                'provider' => 'mrfox',
            ]);
        }

        // Save user message
        $userMsg = $session->messages()->create([
            'role' => 'user',
            'message' => $validated['message'],
            'provider' => 'user',
        ]);

        // Load conversation history
        $history = $session->messages()->oldest()->get()->map(fn (AssistantMessage $m) => [
            'role' => $m->role,
            'content' => $m->message,
        ])->all();

        // Run Mr. Fox Agent
        $result = $this->agent->handle(
            user: $user,
            workspace: $workspace,
            messages: $history,
            conversationId: (string) $session->id,
            activePage: $activePage
        );

        // Save assistant response message
        $assistantMsg = $session->messages()->create([
            'role' => 'assistant',
            'message' => $result['reply'] ?: 'Executed requested actions.',
            'provider' => $result['provider'],
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'user_message' => $userMsg,
            'assistant_message' => $assistantMsg,
            'reply' => $result['reply'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'tools_executed' => $result['tools_executed'],
            'action_proposals' => $result['action_proposals'],
            'evidence' => $result['evidence'],
            'duration_ms' => $result['duration_ms'],
        ]);
    }

    public function getInsights(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();
        $context = $this->contextService->createToolContext($user, $workspace);

        $insights = $this->insightService->generateInsights($context);

        return response()->json([
            'success' => true,
            'insights' => $insights,
        ]);
    }

    public function getConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();

        $sessions = AssistantSession::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace?->id)
            ->whereNull('archived_at')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'conversations' => $sessions,
        ]);
    }

    public function getConversationMessages(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();

        $session = AssistantSession::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace?->id)
            ->with(['messages' => fn ($q) => $q->oldest()])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'conversation' => $session,
            'messages' => $session->messages,
        ]);
    }

    public function approveAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();
        $context = $this->contextService->createToolContext($user, $workspace);

        $result = $this->approvalService->approveAndExecute($id, $user, $context);

        return response()->json([
            'success' => $result->success,
            'summary' => $result->summary,
            'data' => $result->data,
            'evidence' => $result->evidence,
            'error' => $result->error,
        ], $result->success ? 200 : 422);
    }

    public function rejectAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();

        $rejected = $this->approvalService->reject($id, $user, $workspace?->id);

        return response()->json([
            'success' => $rejected,
            'message' => $rejected ? 'Action proposal rejected.' : 'Proposal not found or already processed.',
        ], $rejected ? 200 : 404);
    }
}
