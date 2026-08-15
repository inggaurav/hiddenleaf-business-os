<?php

namespace App\Domain\Communications\DTO;

class OutgoingMessage
{
    public function __construct(
        public string $recipient,
        public string $bodyText,
        public ?string $subject = null,
        public ?string $bodyHtml = null,
        public array $attachments = [],
        public ?string $templateName = null,
        public array $templateParameters = [],
        public ?string $idempotencyKey = null,
        public array $metadata = []
    ) {}
}
