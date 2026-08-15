<?php

namespace App\Http\Controllers\MrFox;

use App\Domain\MrFox\Agent\MrFoxAgent;
use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Insights\BusinessInsightService;
use App\Domain\MrFox\Observability\MrFoxUsageService;
use App\Http\Controllers\Controller;
use App\Models\MrFoxConversation;
use App\Models\MrFoxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MrFoxChatController extends Controller
{
    public function __construct(
        private MrFoxAgent $agent,
        private BusinessInsightService $insightService,
        private ActionApprovalService $approvalService,
        private BusinessContextService $contextService,
        private MrFoxUsageService $usageService
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable'],
            'active_page' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();
        $activePage = $validated['active_page'] ?? 'Dashboard';

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'error' => 'No active workspace found for this user context.',
            ], 422);
        }

        // Quota check guardrail
        if ($this->usageService->hasExceededMonthlyQuota($workspace->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Monthly AI token quota has been exceeded for this workspace.',
            ], 429);
        }

        // Resolve or create persistent conversation
        $conversationId = $validated['conversation_id'] ?? null;
        $conversation = null;

        if (! empty($conversationId)) {
            $conversation = MrFoxConversation::where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->where('id', $conversationId)
                ->first();

            if (! $conversation) {
                return response()->json([
                    'success' => false,
                    'error' => 'Conversation not found in this workspace.',
                ], 404);
            }
        }

        if (! $conversation) {
            $conversation = MrFoxConversation::create([
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'title' => Str::limit($validated['message'], 40, '...'),
                'active_page' => $activePage,
            ]);
        }

        // Persist inbound user message
        $userMessage = MrFoxMessage::create([
            'conversation_id' => $conversation->id,
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        // Load recent messages for context window management (max 10 past messages)
        $pastMessages = MrFoxMessage::where('conversation_id', $conversation->id)
            ->where('id', '!=', $userMessage->id)
            ->latest('id')
            ->take(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->all();

        $messagesPayload = array_merge($pastMessages, [
            ['role' => 'user', 'content' => $validated['message']],
        ]);

        // Execute agent orchestrator
        $result = $this->agent->handle(
            $user,
            $workspace,
            $messagesPayload,
            (string) $conversation->id,
            $activePage
        );

        // Persist assistant reply
        $assistantMsg = MrFoxMessage::create([
            'conversation_id' => $conversation->id,
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
            'role' => 'assistant',
            'content' => $result['reply'] ?? '',
            'tool_calls' => ! empty($result['tools_executed']) ? $result['tools_executed'] : null,
            'tool_results' => ! empty($result['action_proposals']) ? $result['action_proposals'] : null,
            'evidence' => ! empty($result['evidence']) ? $result['evidence'] : null,
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'session_id' => $conversation->id,
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

        if (! $workspace) {
            return response()->json(['success' => false, 'error' => 'No active workspace.'], 422);
        }

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

        if (! $workspace) {
            return response()->json(['success' => false, 'conversations' => []]);
        }

        $conversations = MrFoxConversation::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->latest('updated_at')
            ->take(30)
            ->get();

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    public function getConversationMessages(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();

        if (! $workspace) {
            return response()->json(['success' => false, 'error' => 'No active workspace.'], 422);
        }

        $conversation = MrFoxConversation::where('id', $id)
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $conversation) {
            return response()->json(['success' => false, 'error' => 'Conversation not found in workspace.'], 404);
        }

        $messages = MrFoxMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function approveAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace') ?? $user->workspaces()->first();

        if (! $workspace) {
            return response()->json(['success' => false, 'error' => 'No active workspace.'], 422);
        }

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

        if (! $workspace) {
            return response()->json(['success' => false, 'error' => 'No active workspace.'], 422);
        }

        $rejected = $this->approvalService->reject($id, $user, $workspace->id);

        return response()->json([
            'success' => $rejected,
            'message' => $rejected ? 'Action proposal rejected.' : 'Proposal not found or already processed.',
        ], $rejected ? 200 : 404);
    }
}
