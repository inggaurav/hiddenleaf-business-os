<?php

namespace App\Http\Controllers;

use App\Models\AIAgentChatMessage;
use App\Models\AIAgentChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AIAgentChatController extends Controller
{
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:ai_agent_chat_sessions,id',
            'message' => 'required|string',
        ]);

        $session = AIAgentChatSession::findOrFail($validated['session_id']);
        if ($session->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Store user message
        $userMsg = AIAgentChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'message' => $validated['message'],
        ]);

        // Generate response
        $reply = 'I received your message: "'.$validated['message'].'". How can I assist you with your business operations today?';

        $assistantMsg = AIAgentChatMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'message' => $reply,
        ]);

        return response()->json([
            'success' => true,
            'user_message' => $userMsg,
            'assistant_message' => $assistantMsg,
        ]);
    }
}
