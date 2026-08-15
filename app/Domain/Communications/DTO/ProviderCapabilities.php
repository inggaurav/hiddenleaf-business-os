<?php

namespace App\Domain\Communications\DTO;

class ProviderCapabilities
{
    public function __construct(
        public bool $canRead = true,
        public bool $canSend = true,
        public bool $canReply = true,
        public bool $supportsThreads = true,
        public bool $supportsAttachments = true,
        public bool $supportsTemplates = false,
        public bool $supportsWebhooks = true,
        public bool $supportsOAuth = false,
        public bool $requiresCustomerWindow = false
    ) {}
}
