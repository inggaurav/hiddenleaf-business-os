<?php

namespace App\Domain\Communications\Sync;

use App\Domain\Communications\Matching\AttentionPriorityCalculator;
use App\Domain\Communications\Matching\IdentityMatcher;
use App\Domain\Communications\Matching\InteractionClassificationEngine;
use App\Domain\Communications\Providers\CommunicationProviderRegistry;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use Illuminate\Support\Facades\DB;

class CommunicationSyncService
{
    public function __construct(
        private CommunicationProviderRegistry $providerRegistry,
        private InteractionClassificationEngine $classifier,
        private AttentionPriorityCalculator $priorityCalculator,
        private IdentityMatcher $identityMatcher
    ) {}

    /**
     * Synchronize a specific communication account.
     *
     * @return array{synced_count: int, status: string, error: ?string}
     */
    public function syncAccount(CommunicationAccount $account): array
    {
        if (! $account->enabled) {
            return ['synced_count' => 0, 'status' => 'disabled', 'error' => null];
        }

        $provider = $this->providerRegistry->get($account->provider);
        $syncResult = $provider->sync($account);

        if (! $syncResult->success) {
            $account->update([
                'status' => 'error',
                'last_error' => $syncResult->errorMessage,
            ]);

            return ['synced_count' => 0, 'status' => 'error', 'error' => $syncResult->errorMessage];
        }

        $syncedCount = 0;

        foreach ($syncResult->messages as $msgData) {
            $threadId = $msgData['thread_id'];
            $msgId = $msgData['message_id'];

            // Ingest within transaction
            DB::transaction(function () use ($account, $msgData, $threadId, $msgId, &$syncedCount) {
                // Classify text
                $classification = $this->classifier->classify((string) ($msgData['body_text'] ?? ''), $account->provider);
                $priority = $this->priorityCalculator->calculate([
                    'sentiment' => $classification['sentiment'],
                    'intent' => $classification['intent'],
                    'urgency' => $classification['urgency'],
                    'risk' => $classification['risk'],
                    'unread' => true,
                ]);

                // Match identity to CRM
                $match = $this->identityMatcher->match(
                    $account->organization_id,
                    $account->workspace_id,
                    $msgData['sender_identifier'] ?? null
                );

                // Find or create conversation
                $conv = CommunicationConversation::firstOrCreate([
                    'account_id' => $account->id,
                    'external_thread_id' => $threadId,
                ], [
                    'organization_id' => $account->organization_id,
                    'workspace_id' => $account->workspace_id,
                    'provider' => $account->provider,
                    'subject' => $msgData['subject'] ?? 'Conversation',
                    'participant_name' => $msgData['sender_name'] ?? 'Contact',
                    'participant_identifier' => $msgData['sender_identifier'] ?? 'contact',
                    'priority_score' => $priority,
                    'sentiment' => $classification['sentiment'],
                    'intent' => $classification['intent'],
                    'linked_entity_type' => $match['entity_type'],
                    'linked_entity_id' => $match['entity_id'],
                ]);

                // Avoid duplicate message
                $exists = CommunicationMessage::where('conversation_id', $conv->id)
                    ->where('provider_message_id', $msgId)
                    ->exists();

                if (! $exists) {
                    CommunicationMessage::create([
                        'organization_id' => $account->organization_id,
                        'workspace_id' => $account->workspace_id,
                        'conversation_id' => $conv->id,
                        'provider_message_id' => $msgId,
                        'direction' => 'inbound',
                        'sender_name' => $msgData['sender_name'] ?? 'Contact',
                        'sender_identifier' => $msgData['sender_identifier'] ?? 'contact',
                        'body_text' => $msgData['body_text'] ?? '',
                        'body_html' => $msgData['body_html'] ?? null,
                        'delivery_status' => 'delivered',
                        'sent_at' => $msgData['sent_at'] ?? now(),
                        'metadata' => $msgData['metadata'] ?? null,
                    ]);

                    $conv->update([
                        'last_message_preview' => substr((string) ($msgData['body_text'] ?? ''), 0, 200),
                        'last_message_at' => $msgData['sent_at'] ?? now(),
                        'unread_count' => $conv->unread_count + 1,
                        'priority_score' => $priority,
                    ]);

                    $syncedCount++;
                }
            });
        }

        $account->update([
            'status' => 'healthy',
            'last_synced_at' => now(),
            'last_error' => null,
            'sync_cursor' => $syncResult->nextCursor?->cursor,
        ]);

        return ['synced_count' => $syncedCount, 'status' => 'healthy', 'error' => null];
    }
}
