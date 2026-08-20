<?php

namespace App\Domain\Communications\Webhooks;

use App\Domain\Communications\Matching\AttentionPriorityCalculator;
use App\Domain\Communications\Matching\IdentityMatcher;
use App\Domain\Communications\Matching\InteractionClassificationEngine;
use App\Domain\Communications\Providers\CommunicationProviderRegistry;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunicationWebhookService
{
    public function __construct(
        private CommunicationProviderRegistry $providerRegistry,
        private InteractionClassificationEngine $classifier,
        private AttentionPriorityCalculator $priorityCalculator,
        private IdentityMatcher $identityMatcher
    ) {}

    /**
     * Process incoming webhook for any channel.
     *
     * @return array{success: bool, processed_count: int, message: string}
     */
    public function handleWebhook(string $providerName, array $headers, string $payload): array
    {
        if (! $this->providerRegistry->has($providerName)) {
            return ['success' => false, 'processed_count' => 0, 'message' => "Unknown provider '{$providerName}'"];
        }

        $provider = $this->providerRegistry->get($providerName);
        $data = json_decode($payload, true) ?: [];

        // 1. Process WhatsApp Webhooks
        if ($providerName === 'whatsapp') {
            return $this->handleWhatsAppWebhook($provider, $headers, $payload, $data);
        }

        // 2. Process Slack Events
        if ($providerName === 'slack') {
            return $this->handleSlackWebhook($provider, $headers, $payload, $data);
        }

        return ['success' => true, 'processed_count' => 0, 'message' => 'Acknowledged'];
    }

    private function handleWhatsAppWebhook($provider, array $headers, string $payload, array $data): array
    {
        $entries = $data['entry'] ?? [];
        $processed = 0;

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];

                if (! $phoneNumberId || empty($messages)) {
                    continue;
                }

                // Resolve account strictly from configured database accounts
                $account = CommunicationAccount::where('provider', 'whatsapp')
                    ->where('external_account_id', $phoneNumberId)
                    ->where('enabled', true)
                    ->first();

                if (! $account) {
                    Log::warning('WhatsApp webhook received for unmapped phone_number_id', ['phone_number_id' => $phoneNumberId]);

                    continue;
                }

                // Verify signature if secret configured
                $webhookSecret = $account->metadata['webhook_secret'] ?? config('services.whatsapp.webhook_secret');
                if (! $provider->verifyWebhook($headers, $payload, $webhookSecret)) {
                    return ['success' => false, 'processed_count' => 0, 'message' => 'Invalid webhook signature'];
                }

                foreach ($messages as $msg) {
                    $fromPhone = $msg['from'] ?? '';
                    $msgId = $msg['id'] ?? uniqid('wa_in_');
                    $bodyText = $msg['text']['body'] ?? ($msg['type'] ?? 'media');
                    $senderName = $contacts[0]['profile']['name'] ?? $fromPhone;

                    DB::transaction(function () use ($account, $fromPhone, $msgId, $bodyText, $senderName, &$processed) {
                        $classification = $this->classifier->classify($bodyText, 'whatsapp');
                        $priority = $this->priorityCalculator->calculate([
                            'sentiment' => $classification['sentiment'],
                            'intent' => $classification['intent'],
                            'urgency' => $classification['urgency'],
                            'risk' => $classification['risk'],
                            'unread' => true,
                        ]);

                        $match = $this->identityMatcher->match($account->organization_id, $account->workspace_id, null, $fromPhone);

                        $conv = CommunicationConversation::firstOrCreate([
                            'account_id' => $account->id,
                            'external_thread_id' => $fromPhone,
                        ], [
                            'organization_id' => $account->organization_id,
                            'workspace_id' => $account->workspace_id,
                            'provider' => 'whatsapp',
                            'subject' => "WhatsApp: +{$fromPhone}",
                            'participant_name' => $senderName,
                            'participant_identifier' => "+{$fromPhone}",
                            'priority_score' => $priority,
                            'sentiment' => $classification['sentiment'],
                            'intent' => $classification['intent'],
                            'linked_entity_type' => $match['entity_type'],
                            'linked_entity_id' => $match['entity_id'],
                        ]);

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
                                'sender_name' => $senderName,
                                'sender_identifier' => "+{$fromPhone}",
                                'body_text' => $bodyText,
                                'delivery_status' => 'delivered',
                                'sent_at' => now(),
                            ]);

                            $conv->update([
                                'last_message_preview' => substr($bodyText, 0, 200),
                                'last_message_at' => now(),
                                'unread_count' => $conv->unread_count + 1,
                                'priority_score' => $priority,
                            ]);

                            $processed++;
                        }
                    });
                }
            }
        }

        return ['success' => true, 'processed_count' => $processed, 'message' => 'Processed WhatsApp webhook'];
    }

    private function handleSlackWebhook($provider, array $headers, string $payload, array $data): array
    {
        // URL Verification Challenge
        if (($data['type'] ?? '') === 'url_verification') {
            return ['success' => true, 'processed_count' => 0, 'message' => $data['challenge'] ?? ''];
        }

        $teamId = $data['team_id'] ?? null;
        $event = $data['event'] ?? [];

        if (! $teamId || empty($event)) {
            return ['success' => true, 'processed_count' => 0, 'message' => 'Ignored non-event payload'];
        }

        $account = CommunicationAccount::where('provider', 'slack')
            ->where('external_account_id', $teamId)
            ->where('enabled', true)
            ->first();

        if (! $account) {
            return ['success' => false, 'processed_count' => 0, 'message' => 'Unmapped Slack workspace team_id'];
        }

        $signingSecret = $account->metadata['signing_secret'] ?? config('services.slack.signing_secret');
        if (! $provider->verifyWebhook($headers, $payload, $signingSecret)) {
            return ['success' => false, 'processed_count' => 0, 'message' => 'Invalid Slack signature or expired timestamp'];
        }

        // Ingest message event if not from a bot
        if (($event['type'] ?? '') === 'message' && empty($event['bot_id'])) {
            $channel = $event['channel'] ?? '';
            $ts = $event['ts'] ?? uniqid('slack_msg_');
            $user = $event['user'] ?? 'Slack User';
            $text = $event['text'] ?? '';

            DB::transaction(function () use ($account, $channel, $ts, $user, $text) {
                $classification = $this->classifier->classify($text, 'slack');
                $priority = $this->priorityCalculator->calculate([
                    'sentiment' => $classification['sentiment'],
                    'intent' => $classification['intent'],
                    'urgency' => $classification['urgency'],
                    'risk' => $classification['risk'],
                    'unread' => true,
                ]);

                $conv = CommunicationConversation::firstOrCreate([
                    'account_id' => $account->id,
                    'external_thread_id' => $channel,
                ], [
                    'organization_id' => $account->organization_id,
                    'workspace_id' => $account->workspace_id,
                    'provider' => 'slack',
                    'subject' => "Slack Channel: #{$channel}",
                    'participant_name' => $user,
                    'participant_identifier' => $user,
                    'priority_score' => $priority,
                    'sentiment' => $classification['sentiment'],
                    'intent' => $classification['intent'],
                ]);

                $exists = CommunicationMessage::where('conversation_id', $conv->id)
                    ->where('provider_message_id', $ts)
                    ->exists();

                if (! $exists) {
                    CommunicationMessage::create([
                        'organization_id' => $account->organization_id,
                        'workspace_id' => $account->workspace_id,
                        'conversation_id' => $conv->id,
                        'provider_message_id' => $ts,
                        'direction' => 'inbound',
                        'sender_name' => $user,
                        'sender_identifier' => $user,
                        'body_text' => $text,
                        'delivery_status' => 'delivered',
                        'sent_at' => now(),
                    ]);

                    $conv->update([
                        'last_message_preview' => substr($text, 0, 200),
                        'last_message_at' => now(),
                        'unread_count' => $conv->unread_count + 1,
                        'priority_score' => $priority,
                    ]);
                }
            });
        }

        return ['success' => true, 'processed_count' => 1, 'message' => 'Processed Slack event'];
    }
}
