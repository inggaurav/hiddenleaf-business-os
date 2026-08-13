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
        $this->authorizeTicket($ticket);
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
        $reply->loadMissing('ticket');
        $this->authorizeTicket($reply->ticket);
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $reply->user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }

        $reply->delete();

        return back()->with('success', 'Reply deleted.');
    }

    private function authorizeTicket(HelpdeskTicket $ticket): void
    {
        abort_unless(
            (int) $ticket->workspace_id === (int) session('active_workspace_id')
            && (int) $ticket->organization_id === (int) session('active_organization_id'),
            404,
        );
    }
}
