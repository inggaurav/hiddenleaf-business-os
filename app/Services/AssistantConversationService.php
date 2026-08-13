<?php

namespace App\Services;

use App\Contracts\AssistantProviderContract;
use App\Models\AssistantMessage;
use App\Models\AssistantSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssistantConversationService
{
    public function __construct(private AssistantProviderContract $provider) {}

    public function sessions(User $user, int $workspaceId): Collection
    {
        return AssistantSession::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->whereNull('archived_at')
            ->latest()
            ->get();
    }

    public function create(User $user, int $workspaceId, string $title): AssistantSession
    {
        return AssistantSession::create([
            'title' => $title,
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
            'provider' => $this->provider->name(),
        ]);
    }

    public function ownedSession(User $user, int $workspaceId, int $sessionId): AssistantSession
    {
        return AssistantSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();
    }

    public function send(AssistantSession $session, string $content): array
    {
        return DB::transaction(function () use ($session, $content): array {
            $userMessage = $session->messages()->create([
                'role' => 'user',
                'message' => $content,
                'provider' => $this->provider->name(),
            ]);
            $history = $session->messages()->oldest()->get()->map(fn (AssistantMessage $message) => [
                'role' => $message->role,
                'content' => $message->message,
            ])->all();
            $reply = $this->provider->respond($history, [
                'workspace_id' => $session->workspace_id,
                'user_id' => $session->user_id,
            ]);
            $assistantMessage = $session->messages()->create([
                'role' => 'assistant',
                'message' => $reply,
                'provider' => $this->provider->name(),
            ]);

            return [$userMessage, $assistantMessage];
        });
    }

    public function archive(AssistantSession $session): void
    {
        $session->update(['archived_at' => now()]);
    }

    public function delete(AssistantSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $session->messages()->delete();
            $session->delete();
        });
    }
}
