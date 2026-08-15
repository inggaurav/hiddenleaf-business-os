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
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudProvider implements CommunicationProviderContract
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            canRead: true,
            canSend: true,
            canReply: true,
            supportsThreads: true,
            supportsAttachments: true,
            supportsTemplates: true,
            supportsWebhooks: true,
            supportsOAuth: false,
            requiresCustomerWindow: true
        );
    }

    public function sync(CommunicationAccount $account, ?SyncCursor $cursor = null): SyncResult
    {
        return new SyncResult(success: true, messages: []);
    }

    public function send(CommunicationAccount $account, OutgoingMessage $message): ProviderMessageResult
    {
        $accessToken = $account->access_token;
        $phoneNumberId = $account->external_account_id;

        if (empty($accessToken) || empty($phoneNumberId)) {
            return ProviderMessageResult::failure('WhatsApp Cloud credentials (Access Token or Phone Number ID) missing.');
        }

        $to = preg_replace('/\D/', '', $message->recipient);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ];

        if ($message->templateName) {
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $message->templateName,
                'language' => ['code' => 'en_US'],
                'components' => ! empty($message->templateParameters) ? [
                    [
                        'type' => 'body',
                        'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $message->templateParameters),
                    ],
                ] : [],
            ];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['preview_url' => false, 'body' => $message->bodyText];
        }

        try {
            $response = Http::withToken($accessToken)
                ->connectTimeout(5)
                ->timeout(20)
                ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $json = $response->json();
                $msgId = $json['messages'][0]['id'] ?? uniqid('wa_msg_');

                return ProviderMessageResult::success($msgId, 'sent');
            }

            return ProviderMessageResult::failure("WhatsApp API Error: {$response->status()} - " . ($response->json('error.message') ?? $response->body()));
        } catch (\Throwable $e) {
            return ProviderMessageResult::failure($e->getMessage());
        }
    }

    public function reply(CommunicationAccount $account, CommunicationConversation $conversation, OutgoingMessage $message): ProviderMessageResult
    {
        $lastCustomerMsgAt = $conversation->last_message_at ? Carbon::parse($conversation->last_message_at) : null;
        if ($lastCustomerMsgAt && $lastCustomerMsgAt->diffInHours(now()) >= 24 && empty($message->templateName)) {
            return ProviderMessageResult::failure('WhatsApp 24-hour customer service window expired. An approved Message Template is required to contact this recipient.');
        }

        return $this->send($account, $message);
    }

    public function verifyWebhook(array $headers, string $payload, ?string $secret = null): bool
    {
        if (empty($secret)) {
            return true;
        }

        $rawHeader = $headers['x-hub-signature-256'] ?? '';
        $signatureHeader = is_array($rawHeader) ? ($rawHeader[0] ?? '') : $rawHeader;

        if (empty($signatureHeader)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, (string) $signatureHeader);
    }
}
