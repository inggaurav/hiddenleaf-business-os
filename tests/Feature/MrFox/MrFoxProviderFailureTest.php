<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\Providers\GeminiProvider;
use App\Domain\MrFox\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MrFoxProviderFailureTest extends TestCase
{
    public function test_openai_provider_handles_429_rate_limit_and_normalizes_error(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded: Please wait before sending more requests.',
                    'type' => 'requests',
                ],
            ], 429),
        ]);

        $provider = new OpenAiProvider('sk-dummy-key-1234567890');
        $request = new AiRequest(messages: [['role' => 'user', 'content' => 'Hello']]);

        $response = $provider->chat($request);

        $this->assertStringContainsString('AI service temporarily unavailable', $response->content);
        $this->assertEquals('openai', $response->provider);
    }

    public function test_gemini_provider_handles_server_error_and_normalizes_message(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Internal error'], 500),
        ]);

        $provider = new GeminiProvider('dummy-gemini-key');
        $request = new AiRequest(messages: [['role' => 'user', 'content' => 'Hello']]);

        $response = $provider->chat($request);

        $this->assertStringContainsString('Gemini AI service error', $response->content);
        $this->assertEquals('gemini', $response->provider);
    }

    public function test_provider_without_credentials_returns_clean_configuration_message(): void
    {
        $provider = new OpenAiProvider(null);
        $request = new AiRequest(messages: [['role' => 'user', 'content' => 'Hello']]);

        $response = $provider->chat($request);

        $this->assertStringContainsString('AI provider credentials are not configured', $response->content);
    }
}
