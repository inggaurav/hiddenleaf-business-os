<?php

namespace App\Http\Controllers;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HelpdeskTicketController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $tickets = HelpdeskTicket::with(['category', 'creator'])
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('ticket_id', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        $categories = HelpdeskCategory::when($wsId, fn ($q) => $q->where('workspace_id', $wsId))->get();

        return Inertia::render('Helpdesk/Tickets/Index', [
            'tickets' => $tickets,
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        $wsId = session('active_workspace_id');
        $categories = HelpdeskCategory::when($wsId, fn ($q) => $q->where('workspace_id', $wsId))->get();

        return Inertia::render('Helpdesk/Tickets/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category_id' => ['nullable', Rule::exists('helpdesk_categories', 'id')->where('workspace_id', session('active_workspace_id'))],
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
            'attachments' => 'nullable|array',
        ]);

        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');
        $user = Auth::user();

        $ticketId = strtoupper(substr(uniqid('HD-'), -8));

        HelpdeskTicket::create([
            'ticket_id' => $ticketId,
            'name' => $user->name,
            'email' => $user->email,
            'category_id' => $validated['category_id'] ?? null,
            'subject' => $validated['subject'],
            'status' => 'open',
            'priority' => $validated['priority'],
            'description' => $validated['description'],
            'attachments' => $validated['attachments'] ?? [],
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'created_by' => $user->id,
        ]);

        return redirect()->route('helpdesk-tickets.index')->with('success', 'Ticket created successfully.');
    }

    public function show(HelpdeskTicket $helpdeskTicket)
    {
        $this->authorizeTicket($helpdeskTicket);
        $helpdeskTicket->load(['category', 'creator', 'replies.user']);

        return Inertia::render('Helpdesk/Tickets/Show', [
            'ticket' => $helpdeskTicket,
        ]);
    }

    public function edit(HelpdeskTicket $helpdeskTicket)
    {
        $this->authorizeTicket($helpdeskTicket);
        $wsId = session('active_workspace_id');
        $categories = HelpdeskCategory::when($wsId, fn ($q) => $q->where('workspace_id', $wsId))->get();

        return Inertia::render('Helpdesk/Tickets/Edit', [
            'ticket' => $helpdeskTicket,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, HelpdeskTicket $helpdeskTicket)
    {
        $this->authorizeTicket($helpdeskTicket);
        $validated = $request->validate([
            'status' => 'nullable|in:open,in_progress,closed,resolved',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'category_id' => ['nullable', Rule::exists('helpdesk_categories', 'id')->where('workspace_id', session('active_workspace_id'))],
        ]);

        $helpdeskTicket->update(array_filter($validated));

        return redirect()->route('helpdesk-tickets.index')->with('success', 'Ticket updated successfully.');
    }

    public function destroy(HelpdeskTicket $helpdeskTicket)
    {
        $this->authorizeTicket($helpdeskTicket);
        $helpdeskTicket->replies()->delete();
        $helpdeskTicket->delete();

        return redirect()->route('helpdesk-tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    public function today()
    {
        $wsId = session('active_workspace_id');

        $tickets = HelpdeskTicket::where('workspace_id', $wsId)
            ->whereDate('created_at', today())
            ->with(['category', 'creator'])
            ->latest()
            ->get();

        return response()->json($tickets);
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
