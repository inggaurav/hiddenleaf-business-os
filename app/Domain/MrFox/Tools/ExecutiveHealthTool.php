<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\CommandCenter\Health\BusinessHealthService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;

class ExecutiveHealthTool implements MrFoxToolContract
{
    public function __construct(
        private ?BusinessHealthService $healthService = null
    ) {
        $this->healthService = $healthService ?? app(BusinessHealthService::class);
    }

    public function name(): string
    {
        return 'executive.health';
    }

    public function description(): string
    {
        return 'Retrieve deterministic business health scores (0-100), dimension statuses, and contributing signals across finance, CRM, operations, tasks, and communications.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $health = $this->healthService->evaluateHealth($context->user, $context->workspace);

        $data = [
            'overall_score' => $health['overall']->score,
            'overall_status' => $health['overall']->status,
            'dimensions' => array_map(fn ($d) => $d->toArray(), $health['dimensions']),
        ];

        $summary = sprintf(
            'Business Health Evaluation: Overall Score is %d/100 (%s). Dimensions evaluated: %s.',
            $health['overall']->score,
            strtoupper($health['overall']->status),
            implode(', ', array_keys($health['dimensions']))
        );

        $evidence = [];
        foreach ($health['dimensions'] as $dim) {
            foreach ($dim->evidence as $ev) {
                $evidence[] = $ev;
            }
        }

        return ToolResult::success($data, $summary, $evidence);
    }
}
