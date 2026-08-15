<?php

namespace App\Domain\MrFox\Contracts;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;

interface MrFoxToolContract
{
    public function name(): string;

    public function description(): string;

    public function inputSchema(): array;

    public function requiredPermission(): ?string;

    public function requiredModule(): ?string;

    public function riskLevel(): RiskLevel;

    public function execute(ToolContext $context, array $input): ToolResult;
}
