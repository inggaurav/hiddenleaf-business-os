<?php

namespace App\Contracts;

interface AssistantProviderContract
{
    public function name(): string;

    /** @param array<int, array{role: string, content: string}> $messages */
    public function respond(array $messages, array $context = []): string;
}
