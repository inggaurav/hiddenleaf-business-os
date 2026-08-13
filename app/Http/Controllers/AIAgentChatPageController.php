<?php

namespace App\Http\Controllers;

use App\Models\AssistantSession;
use App\Services\AssistantConversationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AIAgentChatPageController extends Controller
{
    public function __construct(private AssistantConversationService $conversations) {}

    public function index(Request $request)
    {
        return Inertia::render('AIAssistant/Index', [
            'sessions' => $this->sessions($request),
        ]);
    }

    public function getSessions(Request $request)
    {
        return response()->json(['sessions' => $this->sessions($request)]);
    }

    public function createSession(Request $request)
    {
        $validated = $request->validate(['title' => ['nullable', 'string', 'max:255']]);
        $session = $this->conversations->create(
            $request->user(),
            (int) $request->session()->get('active_workspace_id'),
            $validated['title'] ?? 'New Chat Session',
        );

        return response()->json(['success' => true, 'session' => $session]);
    }

    public function destroySession(Request $request, AssistantSession $session)
    {
        $owned = $this->owned($request, $session);
        $this->conversations->delete($owned);

        return response()->json(['success' => true]);
    }

    public function archiveSession(Request $request, AssistantSession $session)
    {
        $owned = $this->owned($request, $session);
        $this->conversations->archive($owned);

        return response()->json(['success' => true]);
    }

    public function getMessages(Request $request, AssistantSession $session)
    {
        return response()->json(['messages' => $this->owned($request, $session)->messages()->oldest()->get()]);
    }

    private function sessions(Request $request)
    {
        return $this->conversations->sessions(
            $request->user(),
            (int) $request->session()->get('active_workspace_id'),
        );
    }

    private function owned(Request $request, AssistantSession $session): AssistantSession
    {
        return $this->conversations->ownedSession(
            $request->user(),
            (int) $request->session()->get('active_workspace_id'),
            $session->id,
        );
    }
}
