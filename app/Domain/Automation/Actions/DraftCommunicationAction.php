<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Domain\MrFox\RiskLevel;
use App\Models\AutomationRun;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;

class DraftCommunicationAction implements AutomationActionContract
{
    public function name(): string
    {
        return 'communications.draft_reply';
    }

    public function description(): string
    {
        return 'Save an automated draft reply into a communication thread without sending.';
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::SAFE;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_body'],
            'properties' => [
                'conversation_id' => ['type' => 'integer'],
                'draft_body' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(AutomationRun $run, array $input): array
    {
        $wsId = $run->workspace_id;
        $convId = (int) ($input['conversation_id'] ?? data_get($run->trigger_payload, 'conversation_id') ?? 0);
        $body = (string) ($input['draft_body'] ?? 'Automated draft response.');

        $conv = CommunicationConversation::where('workspace_id', $wsId)->where('id', $convId)->first();
        if (! $conv) {
            return ['success' => false, 'data' => [], 'error' => "Conversation #{$convId} not found."];
        }

        $msg = CommunicationMessage::create([
            'organization_id' => $run->organization_id,
            'workspace_id' => $wsId,
            'conversation_id' => $conv->id,
            'provider_message_id' => uniqid('draft_'),
            'direction' => 'outbound',
            'sender_name' => 'Automated Draft',
            'body_text' => $body,
            'delivery_status' => 'draft',
            'sent_at' => now(),
        ]);

        return [
            'success' => true,
            'data' => ['draft_id' => $msg->id, 'conversation_id' => $conv->id],
            'error' => null,
        ];
    }
}
