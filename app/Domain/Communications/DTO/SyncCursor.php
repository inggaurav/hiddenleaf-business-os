<?php

namespace App\Domain\Communications\DTO;

class SyncCursor
{
    public function __construct(
        public ?string $cursor = null,
        public ?string $historyId = null,
        public ?string $timestamp = null
    ) {}
}
