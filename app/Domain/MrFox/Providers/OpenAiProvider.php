<?php

namespace App\Domain\MrFox\Providers;

use App\Domain\MrFox\Contracts\AiProviderContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderContract
{
    public function __construct(
        private ?string $apiKey = null,
        private string $model = 'gpt-4o',
        private string $baseUrl = 'https://api.openai.com/v1'
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function chat(AiRequest $request): AiResponse
    {
        if (! $this->isAvailable()) {
            return new AiResponse(
                content: 'AI provider credentials are not configured in Workspace Settings.',
                provider: 'openai',
                model: $this->model
            );
        }

        $formattedMessages = [];
        if ($request->systemPrompt) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $request->systemPrompt,
            ];
        }

        foreach ($request->messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $payload = [
            'model' => $request->model ?: $this->model,
            'messages' => $formattedMessages,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ];

        if (! empty($request->tools)) {
            $payload['tools'] = array_map(function ($tool) {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
                    ],
                ];
            }, $request->tools);
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->connectTimeout(5)
                ->timeout(30)
                ->retry(2, 500, throw: false)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                $status = $response->status();
                $errBody = $response->json();
                $errMessage = $errBody['error']['message'] ?? "HTTP error {$status}";

                Log::warning('OpenAI provider request failed', [
                    'status' => $status,
                    'model' => $this->model,
                ]);

                return new AiResponse(
                    content: "AI service temporarily unavailable ({$status}: {$errMessage}).",
                    provider: 'openai',
                    model: $this->model
                );
            }

            $json = $response->json();
            if (! is_array($json) || ! isset($json['choices'][0]['message'])) {
                return new AiResponse(
                    content: 'Received malformed response payload from AI service.',
                    provider: 'openai',
                    model: $this->model
                );
            }

            $choice = $json['choices'][0]['message'];
            $toolCalls = [];

            if (! empty($choice['tool_calls']) && is_array($choice['tool_calls'])) {
                foreach ($choice['tool_calls'] as $tc) {
                    $fnArgs = [];
                    if (isset($tc['function']['arguments'])) {
                        $fnArgs = is_array($tc['function']['arguments'])
                            ? $tc['function']['arguments']
                            : (json_decode($tc['function']['arguments'], true) ?: []);
                    }

                    $toolCalls[] = [
                        'id' => $tc['id'] ?? uniqid('tc_'),
                        'name' => $tc['function']['name'] ?? '',
                        'arguments' => $fnArgs,
                    ];
                }
            }

            return new AiResponse(
                content: $choice['content'] ?? '',
                toolCalls: $toolCalls,
                provider: 'openai',
                model: $json['model'] ?? $this->model,
                inputTokens: (int) ($json['usage']['prompt_tokens'] ?? 0),
                outputTokens: (int) ($json['usage']['completion_tokens'] ?? 0),
                finishReason: $json['choices'][0]['finish_reason'] ?? null
            );
        } catch (\Throwable $e) {
            Log::error('OpenAI exception during chat completion', ['error' => $e->getMessage()]);

            return new AiResponse(
                content: 'Error communicating with AI service. Please try again later.',
                provider: 'openai',
                model: $this->model
            );
        }
    }
}
