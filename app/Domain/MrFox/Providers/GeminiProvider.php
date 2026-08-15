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

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(30)->post($url, [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $request->temperature,
                    'maxOutputTokens' => $request->maxTokens,
                ],
            ]);

            if ($response->failed()) {
                Log::warning('Gemini provider request failed', ['status' => $response->status(), 'body' => $response->body()]);

                return new AiResponse(
                    content: "Gemini error: {$response->status()}",
                    provider: 'gemini',
                    model: $this->model
                );
            }

            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return new AiResponse(
                content: $text,
                provider: 'gemini',
                model: $this->model,
                inputTokens: $json['usageMetadata']['promptTokenCount'] ?? 0,
                outputTokens: $json['usageMetadata']['candidatesTokenCount'] ?? 0
            );
        } catch (\Throwable $e) {
            Log::error('Gemini exception during chat completion', ['error' => $e->getMessage()]);

            return new AiResponse(
                content: "Error communicating with Gemini service: {$e->getMessage()}",
                provider: 'gemini',
                model: $this->model
            );
        }
    }
}
