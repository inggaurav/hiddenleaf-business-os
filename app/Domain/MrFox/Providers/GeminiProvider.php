<?php

namespace App\Domain\MrFox\Providers;

use App\Domain\MrFox\Contracts\AiProviderContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderContract
{
    public function __construct(
        private ?string $apiKey = null,
        private string $model = 'gemini-1.5-pro'
    ) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function chat(AiRequest $request): AiResponse
    {
        if (! $this->isAvailable()) {
            return new AiResponse(
                content: 'Gemini API key is not configured in Workspace Settings.',
                provider: 'gemini',
                model: $this->model
            );
        }

        $contents = [];
        foreach ($request->messages as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content'] ?? '']],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
            ],
        ];

        if ($request->systemPrompt) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $request->systemPrompt]],
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->retry(2, 500, throw: false)
                ->post($url, $payload);

            if ($response->failed()) {
                $status = $response->status();
                Log::warning('Gemini provider request failed', ['status' => $status, 'model' => $this->model]);

                return new AiResponse(
                    content: "Gemini AI service error ({$status}).",
                    provider: 'gemini',
                    model: $this->model
                );
            }

            $json = $response->json();
            if (! is_array($json)) {
                return new AiResponse(
                    content: 'Received malformed response payload from Gemini service.',
                    provider: 'gemini',
                    model: $this->model
                );
            }

            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return new AiResponse(
                content: $text,
                provider: 'gemini',
                model: $this->model,
                inputTokens: (int) ($json['usageMetadata']['promptTokenCount'] ?? 0),
                outputTokens: (int) ($json['usageMetadata']['candidatesTokenCount'] ?? 0)
            );
        } catch (\Throwable $e) {
            Log::error('Gemini exception during chat completion', ['error' => $e->getMessage()]);

            return new AiResponse(
                content: 'Error communicating with Gemini service. Please try again later.',
                provider: 'gemini',
                model: $this->model
            );
        }
    }
}
