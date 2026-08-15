<?php

namespace App\Domain\MrFox\Providers;

use App\Domain\MrFox\Contracts\AiProviderContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProviderContract
{
    public function __construct(
        private ?string $apiKey = null,
        private string $model = 'claude-3-5-sonnet-20241022',
        private string $baseUrl = 'https://api.anthropic.com/v1'
    ) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function chat(AiRequest $request): AiResponse
    {
        if (! $this->isAvailable()) {
            return new AiResponse(
                content: 'Anthropic API key is not configured in Workspace Settings.',
                provider: 'anthropic',
                model: $this->model
            );
        }

        $messages = [];
        foreach ($request->messages as $msg) {
            $messages[] = [
                'role' => ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $payload = [
            'model' => $request->model ?: $this->model,
            'max_tokens' => $request->maxTokens ?: 4096,
            'messages' => $messages,
        ];

        if ($request->systemPrompt) {
            $payload['system'] = $request->systemPrompt;
        }

        if (! empty($request->tools)) {
            $payload['tools'] = array_map(function ($tool) {
                return [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'input_schema' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
                ];
            }, $request->tools);
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->connectTimeout(5)
                ->timeout(30)
                ->retry(2, 500, throw: false)
                ->post("{$this->baseUrl}/messages", $payload);

            if ($response->failed()) {
                $status = $response->status();
                Log::warning('Anthropic request failed', ['status' => $status, 'model' => $this->model]);

                return new AiResponse(
                    content: "Anthropic service temporarily unavailable ({$status}).",
                    provider: 'anthropic',
                    model: $this->model
                );
            }

            $json = $response->json();
            $contentParts = $json['content'] ?? [];
            $textContent = '';
            $toolCalls = [];

            foreach ($contentParts as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $textContent .= $part['text'] ?? '';
                } elseif (($part['type'] ?? '') === 'tool_use') {
                    $toolCalls[] = [
                        'id' => $part['id'] ?? uniqid('anthropic_tool_'),
                        'name' => $part['name'] ?? '',
                        'arguments' => $part['input'] ?? [],
                    ];
                }
            }

            return new AiResponse(
                content: $textContent,
                toolCalls: $toolCalls,
                provider: 'anthropic',
                model: $json['model'] ?? $this->model,
                inputTokens: (int) ($json['usage']['input_tokens'] ?? 0),
                outputTokens: (int) ($json['usage']['output_tokens'] ?? 0)
            );
        } catch (\Throwable $e) {
            Log::error('Anthropic provider exception', ['error' => $e->getMessage()]);

            return new AiResponse(
                content: 'Error communicating with Anthropic service.',
                provider: 'anthropic',
                model: $this->model
            );
        }
    }
}
