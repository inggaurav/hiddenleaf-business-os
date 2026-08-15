<?php

namespace App\Domain\MrFox\DTO;

class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly string $provider = 'unknown',
        public readonly ?string $model = null,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $finishReason = null
    ) {}

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }
}
