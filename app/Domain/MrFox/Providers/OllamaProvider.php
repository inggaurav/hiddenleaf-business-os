<?php

namespace App\Domain\MrFox\Providers;

use App\Domain\MrFox\Contracts\AiProviderContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\AiResponse;

class OllamaProvider implements AiProviderContract
{
    private OpenAiProvider $delegate;

    public function __construct(
        private string $host = 'http://localhost:11434',
        private string $model = 'llama3.2'
    ) {
        $this->delegate = new OpenAiProvider('ollama-local', $model, rtrim($host, '/').'/v1');
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function chat(AiRequest $request): AiResponse
    {
        $res = $this->delegate->chat($request);

        return new AiResponse(
            content: $res->content,
            toolCalls: $res->toolCalls,
            provider: 'ollama',
            model: $res->model,
            inputTokens: $res->inputTokens,
            outputTokens: $res->outputTokens,
            finishReason: $res->finishReason
        );
    }
}
