<?php

namespace App\Http\Controllers;

use App\Models\AIAgentChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AIAgentChatPageController extends Controller
{
    public function index()
    {
        $wsId = session('active_workspace_id');

        $sessions = AIAgentChatSession::where('user_id', Auth::id())
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->get();

        return Inertia::render('AIAssistant/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function getSessions()
    {
        $wsId = session('active_workspace_id');

        $sessions = AIAgentChatSession::where('user_id', Auth::id())
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    public function createSession(Request $request)
    {
        $wsId = session('active_workspace_id');

        $session = AIAgentChatSession::create([
            'title' => $request->input('title', 'New Chat Session'),
            'user_id' => Auth::id(),
            'workspace_id' => $wsId,
        ]);

        return response()->json(['success' => true, 'session' => $session]);
    }

    public function destroySession(AIAgentChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $session->messages()->delete();
        $session->delete();

        return response()->json(['success' => true]);
    }

    public function getMessages(AIAgentChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        return response()->json(['messages' => $session->messages()->oldest()->get()]);
    }
}
