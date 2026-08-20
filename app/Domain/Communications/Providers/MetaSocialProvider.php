<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\CommunicationProviderContract;
use App\Domain\Communications\DTO\OutgoingMessage;
use App\Domain\Communications\DTO\ProviderCapabilities;
use App\Domain\Communications\DTO\ProviderMessageResult;
use App\Domain\Communications\DTO\SyncCursor;
use App\Domain\Communications\DTO\SyncResult;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use Illuminate\Support\Facades\Http;

class MetaSocialProvider implements CommunicationProviderContract
{
    public function __construct(private string $platform = 'facebook') {}

    public function name(): string
    {
        return $this->platform;
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
            supportsWebhooks: true,
            supportsOAuth: true,
            requiresCustomerWindow: false
        );
    }

    public function sync(CommunicationAccount $account, ?SyncCursor $cursor = null): SyncResult
    {
        return new SyncResult(success: true, messages: []);
    }

    public function send(CommunicationAccount $account, OutgoingMessage $message): ProviderMessageResult
    {
        $token = $account->access_token;
        if (empty($token)) {
            return ProviderMessageResult::failure('Meta Page Access Token missing.');
        }

        try {
            $response = Http::withToken($token)
                ->connectTimeout(5)
                ->timeout(20)
                ->post('https://graph.facebook.com/v20.0/me/messages', [
                    'recipient' => ['id' => $message->recipient],
                    'message' => ['text' => $message->bodyText],
                ]);

            if ($response->successful()) {
                return ProviderMessageResult::success($response->json('message_id') ?? uniqid('meta_msg_'), 'sent');
            }

            return ProviderMessageResult::failure('Meta error: '.($response->json('error.message') ?? $response->body()));
        } catch (\Throwable $e) {
            return ProviderMessageResult::failure($e->getMessage());
        }
    }

    public function reply(CommunicationAccount $account, CommunicationConversation $conversation, OutgoingMessage $message): ProviderMessageResult
    {
        return $this->send($account, $message);
    }

    public function verifyWebhook(array $headers, string $payload, ?string $secret = null): bool
    {
        if (empty($secret)) {
            return true;
        }

        $signature = $headers['x-hub-signature-256'][0] ?? $headers['x-hub-signature-256'] ?? '';
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
