<?php

namespace App\Domain\MrFox\DTO;

class AiRequest
{
    public function __construct(
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly ?string $systemPrompt = null,
        public readonly ?string $model = null,
        public readonly float $temperature = 0.2,
        public readonly int $maxTokens = 2048,
        public readonly array $context = []
    ) {}
}
