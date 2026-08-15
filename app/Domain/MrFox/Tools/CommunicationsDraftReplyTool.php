<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\Communications\Actions\CommunicationReplyGenerator;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CommunicationConversation;

class CommunicationsDraftReplyTool implements MrFoxToolContract
{
    public function __construct(private ?CommunicationReplyGenerator $replyGenerator = null)
    {
        $this->replyGenerator = $replyGenerator ?? app(CommunicationReplyGenerator::class);
    }

    public function name(): string
    {
        return 'communications.draft.reply';
    }

    public function description(): string
    {
        return 'Generate an AI-drafted reply to a customer message grounded in workspace Brand Profile with automated quality review.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id'],
            'properties' => [
                'conversation_id' => ['type' => 'integer', 'description' => 'Conversation ID to draft a reply for'],
                'brand_profile_id' => ['type' => 'integer', 'description' => 'Optional specific Brand Profile ID to apply'],
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
        $brandProfileId = ! empty($input['brand_profile_id']) ? (int) $input['brand_profile_id'] : null;

        $conv = CommunicationConversation::where('workspace_id', $wsId)->where('id', $convId)->first();
        if (! $conv) {
            return ToolResult::error("Conversation #{$convId} not found in this workspace.");
        }

        $draft = $this->replyGenerator->generateDraft($context, $conv, $brandProfileId);

        $data = [
            'conversation_id' => $conv->id,
            'recipient' => $conv->participant_name,
            'channel' => $conv->provider,
            'draft_body' => $draft['draft_body'],
            'review_score' => $draft['review_score'],
            'review_status' => $draft['review_status'],
            'suggestions' => $draft['suggestions'],
        ];

        $summary = "Drafted reply for {$conv->participant_name} (Quality Score: {$draft['review_score']}/100 - {$draft['review_status']}).";

        return ToolResult::success($data, $summary, [
            ['type' => 'communication_message', 'id' => $conv->id, 'label' => "Draft: {$conv->participant_name}", 'route' => '/inbox'],
        ]);
    }
}
