<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DatabaseNotificationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $workspaceId = (int) $request->session()->get('active_workspace_id');
        $notifications = $request->user()->notifications()
            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId))
            ->when($validated['unread'] ?? false, fn ($query) => $query->whereNull('read_at'))
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()
                ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId))
                ->count(),
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->whereKey($notification)->first();
        if (! $item) {
            abort(404, 'Notification not found');
        }
        if ($item->workspace_id !== null && session()->has('active_workspace_id')) {
            abort_unless((int) $item->workspace_id === (int) session('active_workspace_id'), 403);
        }
        $item->markAsRead();

        return response()->json(['success' => true, 'id' => $notification]);
    }

    public function markAllRead(Request $request)
    {
        $workspaceId = (int) $request->session()->get('active_workspace_id');
        $request->user()->unreadNotifications()
            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId))
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
