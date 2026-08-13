<?php

namespace App\Http\Controllers;

use App\Models\HelpdeskReply;
use App\Models\HelpdeskTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpdeskReplyController extends Controller
{
    public function store(Request $request, HelpdeskTicket $ticket)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'attachments' => 'nullable|array',
        ]);

        HelpdeskReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'description' => $validated['description'],
            'attachments' => $validated['attachments'] ?? [],
        ]);

        return back()->with('success', 'Reply posted.');
    }

    public function destroy(HelpdeskReply $reply)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $reply->user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }

        $reply->delete();

        return back()->with('success', 'Reply deleted.');
    }
}
