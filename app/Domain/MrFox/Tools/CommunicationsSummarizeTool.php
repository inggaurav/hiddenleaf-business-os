<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\RiskLevel;
use App\Models\CommunicationConversation;

class CommunicationsSummarizeTool implements MrFoxToolContract
{
    public function __construct(private ?ProviderRouter $providerRouter = null)
    {
        $this->providerRouter = $providerRouter ?? app(ProviderRouter::class);
    }

    public function name(): string
    {
        return 'communications.summarize';
    }

    public function description(): string
    {
        return 'Summarize an entire customer thread with extracted decisions, open questions, and recommended action items.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id'],
            'properties' => [
                'conversation_id' => ['type' => 'integer', 'description' => 'ID of the conversation to summarize'],
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

        $messages = $conv->messages()->latest('id')->take(10)->get()->reverse();
        $transcript = '';
        foreach ($messages as $msg) {
            $sender = $msg->direction === 'inbound' ? ($conv->participant_name ?: 'Contact') : 'Agent';
            $transcript .= "{$sender}: {$msg->body_text}\n";
        }

        if (empty($transcript)) {
            $transcript = "Contact: {$conv->last_message_preview}";
        }

        $prompt = "Summarize this customer communication thread concisely.\n" .
            "Format with:\n" .
            "1. Key Summary (2 sentences)\n" .
            "2. Open Questions / Customer Pain Points\n" .
            "3. Action Items for the Team\n\n" .
            "Transcript:\n{$transcript}";

        $provider = $this->providerRouter->resolve($context->workspace);
        $aiRes = $provider->chat(new AiRequest(messages: [
            ['role' => 'user', 'content' => $prompt],
        ]));

        $data = [
            'conversation_id' => $conv->id,
            'participant' => $conv->participant_name,
            'channel' => $conv->provider,
            'summary_text' => $aiRes->content,
        ];

        return ToolResult::success($data, "Summarized conversation with {$conv->participant_name}.", [
            ['type' => 'communication_message', 'id' => $conv->id, 'label' => "Summary: {$conv->participant_name}", 'route' => '/inbox'],
        ]);
    }
}
