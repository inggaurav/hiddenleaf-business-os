<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\CommandCenter\Priorities\BusinessPriorityService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;

class ExecutivePrioritiesTool implements MrFoxToolContract
{
    public function __construct(
        private ?BusinessPriorityService $priorityService = null
    ) {
        $this->priorityService = $priorityService ?? app(BusinessPriorityService::class);
    }

    public function name(): string
    {
        return 'executive.priorities';
    }

    public function description(): string
    {
        return 'Retrieve ranked business priorities based on severity, financial impact, customer urgency, and operational risk.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of priorities to return (default 5, max 20)',
                ],
            ],
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
        $limit = min(max((int) ($input['limit'] ?? 5), 1), 20);
        $priorities = array_slice($this->priorityService->getPriorities($context->user, $context->workspace), 0, $limit);

        $safeData = array_map(fn ($p) => $p->toArray(), $priorities);
        $summary = sprintf('Identified %d executive priorities for immediate attention.', count($priorities));

        $evidence = [];
        foreach ($priorities as $p) {
            foreach ($p->evidence as $ev) {
                $evidence[] = $ev;
            }
        }

        return ToolResult::success($safeData, $summary, $evidence);
    }
}
