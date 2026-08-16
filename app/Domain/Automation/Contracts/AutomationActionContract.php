<?php

namespace App\Domain\Automation\Contracts;

use App\Domain\MrFox\RiskLevel;
use App\Models\AutomationRun;

interface AutomationActionContract
{
    public function name(): string;

    public function description(): string;

    public function requiredPermission(): ?string;

    public function riskLevel(): RiskLevel;

    public function inputSchema(): array;

    /**
     * Execute the deterministic action.
     *
     * @return array{success: bool, data: array, error: ?string, requires_approval?: bool, proposal_id?: int}
     */
    public function execute(AutomationRun $run, array $input): array;
}
