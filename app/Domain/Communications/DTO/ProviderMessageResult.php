<?php

namespace App\Domain\Communications\DTO;

class ProviderMessageResult
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $errorMessage = null,
        public ?string $deliveryStatus = 'sent',
        public array $metadata = []
    ) {}

    public static function success(string $providerMessageId, string $status = 'sent', array $metadata = []): self
    {
        return new self(
            success: true,
            providerMessageId: $providerMessageId,
            deliveryStatus: $status,
            metadata: $metadata
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            deliveryStatus: 'failed'
        );
    }
}
