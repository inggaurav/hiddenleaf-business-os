<?php

namespace App\Domain\MrFox\Providers;

use App\Domain\MrFox\Contracts\AiProviderContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\AiResponse;

class GroqProvider implements AiProviderContract
{
    private OpenAiProvider $delegate;

    public function __construct(
        private ?string $apiKey = null,
        private string $model = 'llama-3.3-70b-versatile'
    ) {
        $this->delegate = new OpenAiProvider($apiKey, $model, 'https://api.groq.com/openai/v1');
    }

    public function name(): string
    {
        return 'groq';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function chat(AiRequest $request): AiResponse
    {
        if (! $this->isAvailable()) {
            return new AiResponse(
                content: 'Groq API key is not configured in Workspace Settings.',
                provider: 'groq',
                model: $this->model
            );
        }

        $res = $this->delegate->chat($request);

        return new AiResponse(
            content: $res->content,
            toolCalls: $res->toolCalls,
            provider: 'groq',
            model: $res->model,
            inputTokens: $res->inputTokens,
            outputTokens: $res->outputTokens,
            finishReason: $res->finishReason
        );
    }
}
