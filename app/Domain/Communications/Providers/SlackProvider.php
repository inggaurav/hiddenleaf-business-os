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

class SlackProvider implements CommunicationProviderContract
{
    public function name(): string
    {
        return 'slack';
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
            return ProviderMessageResult::failure('Slack Bot Token missing.');
        }

        try {
            $payload = [
                'channel' => $message->recipient,
                'text' => $message->bodyText,
            ];

            $response = Http::withToken($token)
                ->connectTimeout(5)
                ->timeout(20)
                ->post('https://slack.com/api/chat.postMessage', $payload);

            if ($response->json('ok') === true) {
                $ts = $response->json('ts') ?? uniqid('slack_ts_');

                return ProviderMessageResult::success($ts, 'sent');
            }

            return ProviderMessageResult::failure('Slack error: '.($response->json('error') ?? 'unknown_error'));
        } catch (\Throwable $e) {
            return ProviderMessageResult::failure($e->getMessage());
        }
    }

    public function reply(CommunicationAccount $account, CommunicationConversation $conversation, OutgoingMessage $message): ProviderMessageResult
    {
        $token = $account->access_token;
        if (empty($token)) {
            return ProviderMessageResult::failure('Slack Bot Token missing.');
        }

        try {
            $payload = [
                'channel' => $conversation->participant_identifier ?: $message->recipient,
                'text' => $message->bodyText,
                'thread_ts' => $conversation->external_thread_id,
            ];

            $response = Http::withToken($token)
                ->connectTimeout(5)
                ->timeout(20)
                ->post('https://slack.com/api/chat.postMessage', $payload);

            if ($response->json('ok') === true) {
                $ts = $response->json('ts') ?? uniqid('slack_ts_');

                return ProviderMessageResult::success($ts, 'sent');
            }

            return ProviderMessageResult::failure('Slack error: '.($response->json('error') ?? 'unknown_error'));
        } catch (\Throwable $e) {
            return ProviderMessageResult::failure($e->getMessage());
        }
    }

    public function verifyWebhook(array $headers, string $payload, ?string $secret = null): bool
    {
        if (empty($secret)) {
            return true;
        }

        $rawTimestamp = $headers['x-slack-request-timestamp'] ?? null;
        $timestamp = is_array($rawTimestamp) ? ($rawTimestamp[0] ?? null) : $rawTimestamp;

        $rawSig = $headers['x-slack-signature'] ?? null;
        $signature = is_array($rawSig) ? ($rawSig[0] ?? null) : $rawSig;

        if (! $timestamp || ! $signature) {
            return false;
        }

        // Replay prevention: reject requests older than 5 minutes (300 seconds)
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $sigBasestring = "v0:{$timestamp}:{$payload}";
        $expected = 'v0='.hash_hmac('sha256', $sigBasestring, $secret);

        return hash_equals($expected, (string) $signature);
    }
}
