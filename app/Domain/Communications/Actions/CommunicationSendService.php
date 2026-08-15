<?php

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\DTO\OutgoingMessage;
use App\Domain\Communications\DTO\ProviderMessageResult;
use App\Domain\Communications\Providers\CommunicationProviderRegistry;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommunicationSendService
{
    public function __construct(private CommunicationProviderRegistry $providerRegistry) {}

    /**
     * Send a communication reply with strict idempotency and concurrency locking.
     */
    public function sendReply(
        CommunicationConversation $conversation,
        string $bodyText,
        ?string $idempotencyKey = null,
        ?string $templateName = null,
        array $templateParameters = []
    ): ProviderMessageResult {
        $idempotencyKey = $idempotencyKey ?: uniqid('idemp_comm_');

        return DB::transaction(function () use ($conversation, $bodyText, $idempotencyKey, $templateName, $templateParameters) {
            // Lock conversation
            $lockedConv = CommunicationConversation::where('id', $conversation->id)->lockForUpdate()->firstOrFail();
            $account = CommunicationAccount::where('id', $lockedConv->account_id)->lockForUpdate()->firstOrFail();

            // Check if message with this idempotency key already exists
            $existingMsg = CommunicationMessage::where('idempotency_key', $idempotencyKey)->first();
            if ($existingMsg) {
                return ProviderMessageResult::success(
                    $existingMsg->provider_message_id,
                    $existingMsg->delivery_status,
                    ['idempotent_duplicate' => true]
                );
            }

            $provider = $this->providerRegistry->get($lockedConv->provider);
            $outgoing = new OutgoingMessage(
                recipient: $lockedConv->participant_identifier ?: 'recipient',
                bodyText: $bodyText,
                subject: $lockedConv->subject,
                templateName: $templateName,
                templateParameters: $templateParameters,
                idempotencyKey: $idempotencyKey
            );

            $result = $provider->reply($account, $lockedConv, $outgoing);

            if ($result->success) {
                CommunicationMessage::create([
                    'organization_id' => $lockedConv->organization_id,
                    'workspace_id' => $lockedConv->workspace_id,
                    'conversation_id' => $lockedConv->id,
                    'provider_message_id' => $result->providerMessageId,
                    'idempotency_key' => $idempotencyKey,
                    'direction' => 'outbound',
                    'sender_name' => $account->display_name,
                    'sender_identifier' => $account->email ?: $account->phone_number,
                    'body_text' => $bodyText,
                    'delivery_status' => $result->deliveryStatus,
                    'sent_at' => now(),
                ]);

                $lockedConv->update([
                    'last_message_preview' => substr($bodyText, 0, 200),
                    'last_message_at' => now(),
                    'status' => 'in_progress',
                ]);
            }

            return $result;
        });
    }
}
