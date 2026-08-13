<?php

namespace App\Http\Controllers;

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

        $messages = ChMessage::where(function ($q) use ($authId, $contactId) {
            $q->where('from_id', $authId)->where('to_id', $contactId);
        })->orWhere(function ($q) use ($authId, $contactId) {
            $q->where('from_id', $contactId)->where('to_id', $authId);
        })->oldest()->get();

        // Mark as seen
        ChMessage::where('from_id', $contactId)
            ->where('to_id', $authId)
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

        $existing = ChFavorite::where('user_id', Auth::id())
            ->where('favorite_id', $validated['user_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['favorite' => false]);
        }

        ChFavorite::create([
            'user_id' => Auth::id(),
            'favorite_id' => $validated['user_id'],
        ]);

        return response()->json(['favorite' => true]);
    }

    public function getFavorites()
    {
        $favorites = ChFavorite::where('user_id', Auth::id())->pluck('favorite_id');
        $users = User::whereIn('id', $favorites)->get();

        return response()->json(['favorites' => $users]);
    }

    public function editMessage(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:ch_messages,id',
            'message' => 'required|string',
        ]);

        $msg = ChMessage::where('id', $validated['id'])->where('from_id', Auth::id())->firstOrFail();
        $msg->update(['body' => $validated['message']]);

        return response()->json(['status' => 'success', 'message' => $msg]);
    }

    public function deleteMessage(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:ch_messages,id',
        ]);

        $msg = ChMessage::where('id', $validated['id'])->where('from_id', Auth::id())->firstOrFail();
        $msg->delete();

        return response()->json(['status' => 'success']);
    }

    public function setOffline()
    {
        return response()->json(['status' => 'success']);
    }

    public function updatePresence()
    {
        return response()->json(['status' => 'success']);
    }

    public function getOnlineUsers()
    {
        return response()->json(['online' => []]);
    }

    public function togglePin(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $existing = ChPinned::where('user_id', Auth::id())
            ->where('pinned_id', $validated['user_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['pinned' => false]);
        }

        ChPinned::create([
            'user_id' => Auth::id(),
            'pinned_id' => $validated['user_id'],
        ]);

        return response()->json(['pinned' => true]);
    }

    public function getPinned()
    {
        $pinned = ChPinned::where('user_id', Auth::id())->pluck('pinned_id');
        $users = User::whereIn('id', $pinned)->get();

        return response()->json(['pinned' => $users]);
    }

    public function checkNewMessages()
    {
        $count = ChMessage::where('to_id', Auth::id())->where('seen', false)->count();

        return response()->json(['new_messages' => $count]);
    }
}
