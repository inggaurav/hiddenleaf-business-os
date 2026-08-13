<?php

namespace App\Http\Controllers;

use App\Contracts\MessengerTransportContract;
use App\Models\ChFavorite;
use App\Models\ChMessage;
use App\Models\ChPinned;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MessengerController extends Controller
{
    public function __construct(private MessengerTransportContract $transport) {}

    public function index(Request $request)
    {
        return Inertia::render('Messenger/Index', [
            'id' => $request->id ?? 0,
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:users,id',
            'message' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $wsId = session('active_workspace_id');
        $this->assertWorkspaceContact((int) $validated['id'], (int) $wsId);

        $attachmentUrl = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('messenger', 'public');
            $attachmentUrl = Storage::url($path);
        }

        $message = ChMessage::create([
            'from_id' => Auth::id(),
            'to_id' => $validated['id'],
            'body' => $validated['message'] ?? '',
            'attachment' => $attachmentUrl,
            'seen' => false,
            'workspace_id' => $wsId,
        ]);
        $this->transport->publish($message);

        return response()->json([
            'status' => 'success',
            'message' => $message,
        ]);
    }

    public function getContacts(Request $request)
    {
        $user = Auth::user();
        $wsId = session('active_workspace_id');

        $contacts = User::where('id', '!=', $user->id)
            ->when($wsId, function ($query) use ($wsId) {
                $query->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $wsId));
            })
            ->select('id', 'name', 'email', 'avatar')
            ->get();

        return response()->json([
            'contacts' => $contacts,
        ]);
    }

    public function getMessages(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        $authId = Auth::id();
        $contactId = $validated['id'];
        $wsId = (int) session('active_workspace_id');
        $this->assertWorkspaceContact((int) $contactId, $wsId);

        $messages = ChMessage::where('workspace_id', $wsId)
            ->where(function ($query) use ($authId, $contactId) {
                $query->where(function ($q) use ($authId, $contactId) {
                    $q->where('from_id', $authId)->where('to_id', $contactId);
                })->orWhere(function ($q) use ($authId, $contactId) {
                    $q->where('from_id', $contactId)->where('to_id', $authId);
                });
            })->oldest()->get();

        // Mark as seen
        ChMessage::where('from_id', $contactId)
            ->where('to_id', $authId)
            ->where('workspace_id', $wsId)
            ->where('seen', false)
            ->update(['seen' => true]);

        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function toggleFavorite(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $wsId = (int) session('active_workspace_id');
        $this->assertWorkspaceContact((int) $validated['user_id'], $wsId);

        $existing = ChFavorite::where('user_id', Auth::id())
            ->where('favorite_id', $validated['user_id'])
            ->where('workspace_id', $wsId)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['favorite' => false]);
        }

        ChFavorite::create([
            'user_id' => Auth::id(),
            'favorite_id' => $validated['user_id'],
            'workspace_id' => $wsId,
        ]);

        return response()->json(['favorite' => true]);
    }

    public function getFavorites()
    {
        $wsId = (int) session('active_workspace_id');
        $favorites = ChFavorite::where('user_id', Auth::id())->where('workspace_id', $wsId)->pluck('favorite_id');
        $users = User::whereIn('id', $favorites)
            ->whereHas('workspaces', fn ($query) => $query->where('workspaces.id', $wsId))
            ->get();

        return response()->json(['favorites' => $users]);
    }

    public function editMessage(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:ch_messages,id',
            'message' => 'required|string',
        ]);

        $msg = ChMessage::where('id', $validated['id'])
            ->where('workspace_id', session('active_workspace_id'))
            ->where('from_id', Auth::id())->firstOrFail();
        $msg->update(['body' => $validated['message']]);

        return response()->json(['status' => 'success', 'message' => $msg]);
    }

    public function deleteMessage(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:ch_messages,id',
        ]);

        $msg = ChMessage::where('id', $validated['id'])
            ->where('workspace_id', session('active_workspace_id'))
            ->where('from_id', Auth::id())->firstOrFail();
        $msg->delete();

        return response()->json(['status' => 'success']);
    }

    public function setOffline()
    {
        cache()->forget('messenger:presence:'.session('active_workspace_id').':'.Auth::id());

        return response()->json(['status' => 'success', 'online' => false]);
    }

    public function updatePresence()
    {
        $this->transport->markPresent((int) session('active_workspace_id'), (int) Auth::id());

        return response()->json(['status' => 'success', 'online' => true]);
    }

    public function getOnlineUsers()
    {
        $wsId = (int) session('active_workspace_id');
        $online = Auth::user()->workspaces()->whereKey($wsId)->firstOrFail()
            ->members()->where('users.id', '!=', Auth::id())->get(['users.id', 'users.name'])
            ->filter(fn (User $user) => $this->transport->isPresent($wsId, $user->id))
            ->values();

        return response()->json(['online' => $online]);
    }

    public function togglePin(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $wsId = (int) session('active_workspace_id');
        $this->assertWorkspaceContact((int) $validated['user_id'], $wsId);

        $existing = ChPinned::where('user_id', Auth::id())
            ->where('pinned_id', $validated['user_id'])
            ->where('workspace_id', $wsId)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['pinned' => false]);
        }

        ChPinned::create([
            'user_id' => Auth::id(),
            'pinned_id' => $validated['user_id'],
            'workspace_id' => $wsId,
        ]);

        return response()->json(['pinned' => true]);
    }

    public function getPinned()
    {
        $wsId = (int) session('active_workspace_id');
        $pinned = ChPinned::where('user_id', Auth::id())->where('workspace_id', $wsId)->pluck('pinned_id');
        $users = User::whereIn('id', $pinned)
            ->whereHas('workspaces', fn ($query) => $query->where('workspaces.id', $wsId))
            ->get();

        return response()->json(['pinned' => $users]);
    }

    public function checkNewMessages()
    {
        $count = ChMessage::where('workspace_id', session('active_workspace_id'))
            ->where('to_id', Auth::id())->where('seen', false)->count();

        return response()->json(['new_messages' => $count]);
    }

    public function toggleMessagePin(Request $request)
    {
        $validated = $request->validate(['id' => ['required', 'integer']]);
        $message = ChMessage::whereKey($validated['id'])
            ->where('workspace_id', session('active_workspace_id'))
            ->where(fn ($query) => $query->where('from_id', Auth::id())->orWhere('to_id', Auth::id()))
            ->firstOrFail();
        $message->update(['pinned_at' => $message->pinned_at ? null : now()]);

        return response()->json(['pinned' => $message->pinned_at !== null, 'message' => $message]);
    }

    private function assertWorkspaceContact(int $contactId, int $workspaceId): void
    {
        abort_if($contactId === (int) Auth::id(), 422, 'You cannot message yourself.');
        abort_unless(
            User::whereKey($contactId)->whereHas('workspaces', fn ($query) => $query->where('workspaces.id', $workspaceId))->exists(),
            404,
        );
    }
}
