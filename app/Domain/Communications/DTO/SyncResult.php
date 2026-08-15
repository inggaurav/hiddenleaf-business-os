<?php

namespace App\Domain\Communications\DTO;

class SyncResult
{
    /**
     * @param  array<array{thread_id: string, message_id: string, sender_name: ?string, sender_identifier: ?string, body_text: string, body_html: ?string, subject: ?string, sent_at: ?string, metadata: array}>  $messages
     */
    public function __construct(
        public bool $success,
        public array $messages = [],
        public ?SyncCursor $nextCursor = null,
        public ?string $errorMessage = null
    ) {}
}
