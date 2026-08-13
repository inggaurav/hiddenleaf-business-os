<?php

namespace App\Http\Controllers;

use App\Services\AssistantConversationService;
use Illuminate\Http\Request;

class AIAgentChatController extends Controller
{
    public function __construct(private AssistantConversationService $conversations) {}

    public function chat(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:20000'],
        ]);
        $session = $this->conversations->ownedSession(
            $request->user(),
            (int) $request->session()->get('active_workspace_id'),
            (int) $validated['session_id'],
        );
        [$userMessage, $assistantMessage] = $this->conversations->send($session, $validated['message']);

        return response()->json([
            'success' => true,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ]);
    }
}
