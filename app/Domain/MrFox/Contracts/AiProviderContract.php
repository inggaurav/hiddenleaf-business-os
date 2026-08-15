<?php

namespace App\Domain\MrFox\Contracts;

use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\AiResponse;

interface AiProviderContract
{
    public function name(): string;

    public function isAvailable(): bool;

    public function chat(AiRequest $request): AiResponse;
}
