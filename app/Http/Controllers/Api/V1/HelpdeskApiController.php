<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use Illuminate\Http\Request;

class HelpdeskApiController extends Controller
{
    public function tickets(Request $request)
    {
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');

        $tickets = HelpdeskTicket::with(['category', 'creator'])
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    public function storeTicket(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category_id' => 'nullable|exists:helpdesk_categories,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
        ]);

        $user = $request->user();
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');
        $orgId = $request->header('X-Organization-ID') ?: session('active_organization_id');

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

    public function ticketDetails(HelpdeskTicket $ticket)
    {
        $ticket->load(['category', 'creator', 'replies.user']);

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
        ]);
    }
}
