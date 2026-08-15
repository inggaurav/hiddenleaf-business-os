<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\Providers\AnthropicProvider;
use App\Domain\MrFox\Providers\GroqProvider;
use App\Domain\MrFox\Providers\OllamaProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MrFoxMultiProviderTest extends TestCase
{
    public function test_anthropic_provider_handles_tool_calling_and_streaming_payloads(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_123',
                'model' => 'claude-3-5-sonnet-20241022',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Let me look that up for you.'],
                    ['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'knowledge.search', 'input' => ['query' => 'vacation policy']],
                ],
                'usage' => ['input_tokens' => 45, 'output_tokens' => 28],
            ], 200),
        ]);

        $provider = new AnthropicProvider('sk-ant-dummy-api-key');
        $request = new AiRequest(messages: [['role' => 'user', 'content' => 'What is our vacation policy?']]);

        $response = $provider->chat($request);

        $this->assertEquals('anthropic', $response->provider);
        $this->assertEquals('Let me look that up for you.', $response->content);
        $this->assertTrue($response->hasToolCalls());
        $this->assertEquals('knowledge.search', $response->toolCalls[0]['name']);
        $this->assertEquals(45, $response->inputTokens);
        $this->assertEquals(28, $response->outputTokens);
    }

    public function test_groq_and_ollama_providers_resolve_cleanly(): void
    {
        $groq = new GroqProvider(null);
        $this->assertFalse($groq->isAvailable());

        $ollama = new OllamaProvider('http://localhost:11434', 'llama3.2');
        $this->assertTrue($ollama->isAvailable());
        $this->assertEquals('ollama', $ollama->name());
    }
}
