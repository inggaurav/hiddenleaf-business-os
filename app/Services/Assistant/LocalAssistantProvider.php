<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantProviderContract;

class LocalAssistantProvider implements AssistantProviderContract
{
    public function name(): string
    {
        return 'local';
    }

    public function respond(array $messages, array $context = []): string
    {
        $latest = collect($messages)->last(fn (array $message) => $message['role'] === 'user');

        return sprintf(
            'I received your request: "%s". Connect a configured assistant provider to generate business-specific analysis.',
            $latest['content'] ?? '',
        );
    }
}
