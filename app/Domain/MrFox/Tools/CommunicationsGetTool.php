<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CommunicationConversation;

class CommunicationsGetTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'communications.get';
    }

    public function description(): string
    {
        return 'Retrieve full details and recent message history for a specific conversation in the Unified Inbox.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id'],
            'properties' => [
                'conversation_id' => ['type' => 'integer', 'description' => 'Unique ID of the conversation'],
            ],
        ];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $convId = (int) ($input['conversation_id'] ?? 0);

        $conv = CommunicationConversation::where('workspace_id', $wsId)->where('id', $convId)->first();
        if (! $conv) {
            return ToolResult::error("Conversation #{$convId} not found in this workspace.");
        }

        $messages = $conv->messages()->latest('id')->take(20)->get()->reverse()->map(fn ($m) => [
            'id' => $m->id,
            'direction' => $m->direction,
            'sender' => $m->sender_name,
            'body' => $m->body_text,
            'status' => $m->delivery_status,
            'sent_at' => optional($m->sent_at)->toIso8601String(),
        ])->values()->all();

        $data = [
            'id' => $conv->id,
            'provider' => $conv->provider,
            'subject' => $conv->subject,
            'participant_name' => $conv->participant_name,
            'participant_identifier' => $conv->participant_identifier,
            'priority_score' => $conv->priority_score,
            'sentiment' => $conv->sentiment,
            'intent' => $conv->intent,
            'status' => $conv->status,
            'messages' => $messages,
        ];

        $summary = "Conversation with {$conv->participant_name} ({$conv->provider}) — {$conv->status} with " . count($messages) . ' recent messages.';

        return ToolResult::success($data, $summary, [
            ['type' => 'communication_message', 'id' => $conv->id, 'label' => "{$conv->provider}: {$conv->participant_name}", 'route' => '/inbox'],
        ]);
    }
}
