<?php

namespace App\Http\Controllers\Communications;

use App\Domain\Communications\Actions\CommunicationReplyGenerator;
use App\Domain\Communications\Actions\CommunicationSendService;
use App\Domain\Communications\Sync\CommunicationSyncService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Http\Controllers\Controller;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnifiedInboxController extends Controller
{
    public function __construct(
        private CommunicationSyncService $syncService,
        private CommunicationSendService $sendService,
        private CommunicationReplyGenerator $replyGenerator,
        private BusinessContextService $contextService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $conversations = CommunicationConversation::where('workspace_id', $wsId)
            ->with(['account', 'assignee'])
            ->latest('last_message_at')
            ->paginate(30);

        $accounts = CommunicationAccount::where('workspace_id', $wsId)->get();

        return Inertia::render('Communications/Inbox', [
            'conversations' => $conversations,
            'accounts' => $accounts,
        ]);
    }

    public function getConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;
        $channel = $request->query('channel');
        $status = $request->query('status');
        $query = $request->query('q');

        $builder = CommunicationConversation::where('workspace_id', $wsId);

        if ($channel && $channel !== 'all') {
            $builder->where('provider', $channel);
        }
        if ($status) {
            $builder->where('status', $status);
        }
        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('participant_name', 'like', "%{$query}%")
                    ->orWhere('participant_identifier', 'like', "%{$query}%")
                    ->orWhere('subject', 'like', "%{$query}%")
                    ->orWhere('last_message_preview', 'like', "%{$query}%");
            });
        }

        $list = $builder->latest('last_message_at')->paginate(25);

        return response()->json($list);
    }

    public function getThread(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $conversation = CommunicationConversation::where('workspace_id', $wsId)
            ->where('id', $id)
            ->with(['account', 'assignee'])
            ->firstOrFail();

        // Mark as read
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        $messages = $conversation->messages()
            ->with('attachments')
            ->orderBy('sent_at', 'asc')
            ->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message_body' => 'required|string|max:10000',
            'idempotency_key' => 'nullable|string|max:100',
            'template_name' => 'nullable|string|max:100',
            'template_parameters' => 'nullable|array',
        ]);

        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $conversation = CommunicationConversation::where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $result = $this->sendService->sendReply(
            $conversation,
            $validated['message_body'],
            $validated['idempotency_key'] ?? null,
            $validated['template_name'] ?? null,
            $validated['template_parameters'] ?? []
        );

        if (! $result->success) {
            return response()->json(['error' => $result->errorMessage], 422);
        }

        return response()->json([
            'success' => true,
            'message_id' => $result->providerMessageId,
            'status' => $result->deliveryStatus,
        ]);
    }

    public function draftReply(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = $user->current_workspace_id;
        $workspace = $user->currentWorkspace;

        $conversation = CommunicationConversation::where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $context = $this->contextService->createToolContext($user, $workspace);
        $draft = $this->replyGenerator->generateDraft($context, $conversation);

        return response()->json($draft);
    }

    public function linkCrm(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|in:lead,deal,customer,vendor,task',
            'entity_id' => 'required|integer',
        ]);

        $user = $request->user();
        $wsId = $user->current_workspace_id;

        $conversation = CommunicationConversation::where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $conversation->update([
            'linked_entity_type' => $validated['entity_type'],
            'linked_entity_id' => $validated['entity_id'],
        ]);

        return response()->json(['success' => true, 'conversation' => $conversation]);
    }
}
