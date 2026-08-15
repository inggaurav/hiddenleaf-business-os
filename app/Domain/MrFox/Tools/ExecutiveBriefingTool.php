<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\CommandCenter\Briefings\ExecutiveBriefingService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;

class ExecutiveBriefingTool implements MrFoxToolContract
{
    public function __construct(
        private ?ExecutiveBriefingService $briefingService = null
    ) {
        $this->briefingService = $briefingService ?? app(ExecutiveBriefingService::class);
    }

    public function name(): string
    {
        return 'executive.briefing';
    }

    public function description(): string
    {
        return 'Generate a daily executive briefing covering top changes, financial snapshot, sales pipeline, urgent communications, operations, and waiting approvals.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', 'yesterday', 'last_7_days'],
                    'description' => 'Time period window for briefing deltas',
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
        $period = $input['period'] ?? 'today';
        $briefing = $this->briefingService->generateBriefing($context->user, $context->workspace, $period);

        return ToolResult::success(
            $briefing->toArray(),
            $briefing->summaryHeadline,
            [['type' => 'briefing', 'label' => 'Executive Briefing', 'route' => '/command-center']]
        );
    }
}
