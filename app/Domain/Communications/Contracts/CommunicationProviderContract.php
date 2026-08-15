<?php

namespace App\Domain\Communications\Contracts;

use App\Domain\Communications\DTO\OutgoingMessage;
use App\Domain\Communications\DTO\ProviderCapabilities;
use App\Domain\Communications\DTO\ProviderMessageResult;
use App\Domain\Communications\DTO\SyncCursor;
use App\Domain\Communications\DTO\SyncResult;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;

interface CommunicationProviderContract
{
    public function name(): string;

    public function capabilities(): ProviderCapabilities;

    public function sync(CommunicationAccount $account, ?SyncCursor $cursor = null): SyncResult;

    public function send(CommunicationAccount $account, OutgoingMessage $message): ProviderMessageResult;

    public function reply(CommunicationAccount $account, CommunicationConversation $conversation, OutgoingMessage $message): ProviderMessageResult;

    public function verifyWebhook(array $headers, string $payload, ?string $secret = null): bool;
}
