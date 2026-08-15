<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\CommandCenter\Recommendations\ExecutiveRecommendationService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;

class ExecutiveRecommendationsTool implements MrFoxToolContract
{
    public function __construct(
        private ?ExecutiveRecommendationService $recommendationService = null
    ) {
        $this->recommendationService = $recommendationService ?? app(ExecutiveRecommendationService::class);
    }

    public function name(): string
    {
        return 'executive.recommendations';
    }

    public function description(): string
    {
        return 'Retrieve actionable, evidence-backed recommendations derived from active business signals.';
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
        $recommendations = $this->recommendationService->getRecommendations($context->user, $context->workspace);
        $safeData = array_map(fn ($r) => $r->toArray(), $recommendations);

        $summary = sprintf('Generated %d executive recommendations backed by operational evidence.', count($recommendations));

        $evidence = [];
        foreach ($recommendations as $rec) {
            foreach ($rec->evidence as $ev) {
                $evidence[] = $ev;
            }
        }

        return ToolResult::success($safeData, $summary, $evidence);
    }
}
