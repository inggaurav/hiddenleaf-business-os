<?php

namespace App\Contracts;

use App\Models\ChMessage;

interface MessengerTransportContract
{
    public function publish(ChMessage $message): void;

    public function markPresent(int $workspaceId, int $userId): void;

    public function isPresent(int $workspaceId, int $userId): bool;
}
