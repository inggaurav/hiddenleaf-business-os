<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpdeskApiController extends Controller
{
    public function tickets(Request $request)
    {
        $wsId = $request->attributes->get('workspace')->id;

        $tickets = HelpdeskTicket::with(['category', 'creator'])
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate(max(1, min((int) $request->input('per_page', 20), 100)));

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    public function storeTicket(Request $request)
    {
        $workspace = $request->attributes->get('workspace');
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category_id' => ['nullable', Rule::exists('helpdesk_categories', 'id')->where('workspace_id', $workspace->id)],
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
        ]);

        $user = $request->user();
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

        $ticketId = strtoupper(substr(uniqid('HD-'), -8));

        $ticket = HelpdeskTicket::create([
            'ticket_id' => $ticketId,
            'name' => $user->name,
            'email' => $user->email,
            'category_id' => $validated['category_id'] ?? null,
            'subject' => $validated['subject'],
            'status' => 'open',
            'priority' => $validated['priority'],
            'description' => $validated['description'],
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
        ], 201);
    }

    public function ticketDetails(Request $request, HelpdeskTicket $ticket)
    {
        abort_unless($ticket->workspace_id === $request->attributes->get('workspace')->id, 404);
        $ticket->load(['category', 'creator', 'replies.user']);

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
        ]);
    }
}
