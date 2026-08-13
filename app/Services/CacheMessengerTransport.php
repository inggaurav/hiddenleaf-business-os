<?php

namespace App\Services;

use App\Contracts\MessengerTransportContract;
use App\Models\ChMessage;
use Illuminate\Support\Facades\Cache;

class CacheMessengerTransport implements MessengerTransportContract
{
    public function publish(ChMessage $message): void
    {
        Cache::put(
            "messenger:latest:{$message->workspace_id}:{$message->to_id}",
            $message->id,
            now()->addMinutes(10),
        );
    }

    public function markPresent(int $workspaceId, int $userId): void
    {
        Cache::put($this->presenceKey($workspaceId, $userId), true, now()->addSeconds(90));
    }

    public function isPresent(int $workspaceId, int $userId): bool
    {
        return Cache::has($this->presenceKey($workspaceId, $userId));
    }

    private function presenceKey(int $workspaceId, int $userId): string
    {
        return "messenger:presence:{$workspaceId}:{$userId}";
    }
}
