<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\Insights\BusinessInsightService;
use App\Domain\MrFox\RiskLevel;

class BusinessAlertsTool implements MrFoxToolContract
{
    public function __construct(private ?BusinessInsightService $insightService = null)
    {
        $this->insightService = $insightService ?? app(BusinessInsightService::class);
    }

    public function name(): string
    {
        return 'business.alerts';
    }

    public function description(): string
    {
        return 'Fetch all active business warnings, overdue receivables, low inventory alerts, overdue tasks, and pending HR actions.';
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
        $insights = $this->insightService->generateInsights($context);

        $evidence = [];
        foreach ($insights as $insight) {
            foreach ($insight['evidence'] ?? [] as $ev) {
                $evidence[] = $ev;
            }
        }

        $summary = sprintf('Identified %d operational business signal(s) requiring attention.', count($insights));

        return ToolResult::success($insights, $summary, array_slice($evidence, 0, 10));
    }
}
