<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\CommunicationProviderContract;
use App\Domain\Communications\DTO\OutgoingMessage;
use App\Domain\Communications\DTO\ProviderCapabilities;
use App\Domain\Communications\DTO\ProviderMessageResult;
use App\Domain\Communications\DTO\SyncCursor;
use App\Domain\Communications\DTO\SyncResult;
use App\Models\ChMessage;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\User;

class InternalMessengerProvider implements CommunicationProviderContract
{
    public function name(): string
    {
        return 'internal';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            canRead: true,
            canSend: true,
            canReply: true,
            supportsThreads: true,
            supportsAttachments: true,
            supportsTemplates: false,
            supportsWebhooks: false,
            supportsOAuth: false,
            requiresCustomerWindow: false
        );
    }

    public function sync(CommunicationAccount $account, ?SyncCursor $cursor = null): SyncResult
    {
        $wsId = $account->workspace_id;
        $userId = $account->user_id;

        $legacyMessages = ChMessage::where('workspace_id', $wsId)
            ->where(function ($q) use ($userId) {
                if ($userId) {
                    $q->where('from_id', $userId)->orWhere('to_id', $userId);
                }
            })
            ->latest('id')
            ->take(50)
            ->get();

        $messages = [];
        foreach ($legacyMessages as $msg) {
            $sender = User::find($msg->from_id);
            $threadId = min($msg->from_id, $msg->to_id) . '_' . max($msg->from_id, $msg->to_id);

            $messages[] = [
                'thread_id' => "internal_{$threadId}",
                'message_id' => "ch_msg_{$msg->id}",
                'sender_name' => $sender?->name ?? 'Team Member',
                'sender_identifier' => $sender?->email ?? "user_{$msg->from_id}",
                'body_text' => (string) $msg->body,
                'body_html' => null,
                'subject' => 'Internal Team Chat',
                'sent_at' => optional($msg->created_at)->toIso8601String() ?: now()->toIso8601String(),
                'metadata' => ['legacy_id' => $msg->id, 'seen' => (bool) $msg->seen],
            ];
        }

        return new SyncResult(success: true, messages: $messages);
    }

    public function send(CommunicationAccount $account, OutgoingMessage $message): ProviderMessageResult
    {
        $fromId = $account->user_id ?: 1;
        $toId = (int) preg_replace('/\D/', '', $message->recipient);

        $legacy = ChMessage::create([
            'from_id' => $fromId,
            'to_id' => $toId,
            'body' => $message->bodyText,
            'workspace_id' => $account->workspace_id,
            'seen' => false,
        ]);

        return ProviderMessageResult::success("ch_msg_{$legacy->id}", 'delivered');
    }

    public function reply(CommunicationAccount $account, CommunicationConversation $conversation, OutgoingMessage $message): ProviderMessageResult
    {
        return $this->send($account, $message);
    }

    public function verifyWebhook(array $headers, string $payload, ?string $secret = null): bool
    {
        return true;
    }
}
