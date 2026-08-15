<?php

namespace App\Domain\MrFox\Providers;

use App\Domain\MrFox\Contracts\AiProviderContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\AiResponse;

class FakeAiProvider implements AiProviderContract
{
    private array $cannedResponses = [];
    private ?\Closure $responseGenerator = null;

    public function name(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function queueResponse(AiResponse $response): void
    {
        $this->cannedResponses[] = $response;
    }

    public function setResponseGenerator(\Closure $callback): void
    {
        $this->responseGenerator = $callback;
    }

    public function chat(AiRequest $request): AiResponse
    {
        if ($this->responseGenerator !== null) {
            return ($this->responseGenerator)($request);
        }

        if (! empty($this->cannedResponses)) {
            return array_shift($this->cannedResponses);
        }

        $messages = $request->messages;
        $lastMessage = ! empty($messages) ? $messages[count($messages) - 1] : [];
        $userText = $lastMessage['content'] ?? '';

        // Check if user asked for a specific tool like leads, stock, or invoices
        if (str_contains(strtolower($userText), 'lead') && ! empty($request->tools)) {
            return new AiResponse(
                content: 'Checking CRM leads...',
                toolCalls: [
                    [
                        'id' => 'call_1',
                        'name' => 'crm.search.leads',
                        'arguments' => ['limit' => 5],
                    ],
                ],
                provider: 'fake',
                model: 'fake-agent-v1'
            );
        }

        if (str_contains(strtolower($userText), 'stock') && ! empty($request->tools)) {
            return new AiResponse(
                content: 'Checking inventory stock levels...',
                toolCalls: [
                    [
                        'id' => 'call_2',
                        'name' => 'inventory.low_stock',
                        'arguments' => [],
                    ],
                ],
                provider: 'fake',
                model: 'fake-agent-v1'
            );
        }

        return new AiResponse(
            content: "Mr. Fox Intelligence received: \"{$userText}\". Operating with enterprise tenant isolation.",
            provider: 'fake',
            model: 'fake-agent-v1',
            inputTokens: 15,
            outputTokens: 20
        );
    }
}
