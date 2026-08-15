<?php

namespace App\Domain\Communications\Actions;

use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\Review\ContentReviewEngine;
use App\Models\CommunicationConversation;
use App\Models\MrFoxBrandProfile;

class CommunicationReplyGenerator
{
    public function __construct(
        private ProviderRouter $providerRouter,
        private ContentReviewEngine $reviewEngine
    ) {}

    /**
     * Generate an AI-powered draft reply grounded in workspace Brand Profile and evaluated by ContentReviewEngine.
     *
     * @return array{draft_body: string, review_score: int, review_status: string, suggestions: array}
     */
    public function generateDraft(ToolContext $context, CommunicationConversation $conversation, ?int $brandProfileId = null): array
    {
        $wsId = $context->getWorkspaceId();
        $profile = MrFoxBrandProfile::where('workspace_id', $wsId)
            ->when($brandProfileId, fn ($q) => $q->where('id', $brandProfileId))
            ->orderByDesc('is_default')
            ->first();

        $brandName = $profile?->name ?? 'Our Company';
        $tone = $profile?->tone_of_voice['archetype'] ?? 'Professional, helpful, and courteous';

        // Extract last few messages for conversation context
        $recentMessages = $conversation->messages()->latest('id')->take(4)->get()->reverse();
        $threadHistory = '';
        foreach ($recentMessages as $msg) {
            $sender = $msg->direction === 'inbound' ? ($conversation->participant_name ?: 'Customer') : 'Agent';
            $threadHistory .= "{$sender}: {$msg->body_text}\n";
        }

        if (empty($threadHistory)) {
            $threadHistory = "Customer: {$conversation->last_message_preview}";
        }

        $prompt = "You are drafting a professional customer service reply for '{$brandName}'.\n" .
            "Brand Persona / Tone: {$tone}\n" .
            "Channel: {$conversation->provider}\n" .
            "Customer Name: {$conversation->participant_name}\n\n" .
            "Conversation History:\n{$threadHistory}\n\n" .
            "Write a concise, helpful, and empathetic reply directly addressing their inquiry or concern. Do not include placeholders.";

        $provider = $this->providerRouter->resolve($context->workspace);
        $aiRes = $provider->chat(new AiRequest(messages: [
            ['role' => 'user', 'content' => $prompt],
        ]));

        $draftBody = trim($aiRes->content);

        // Quality review check
        $review = $this->reviewEngine->review($context, $draftBody, $profile?->id);

        return [
            'draft_body' => $draftBody,
            'review_score' => $review['score'],
            'review_status' => $review['status'],
            'suggestions' => $review['suggestions'],
        ];
    }
}
