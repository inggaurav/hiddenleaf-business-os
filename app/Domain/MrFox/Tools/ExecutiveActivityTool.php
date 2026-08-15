<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\CommandCenter\Activity\ExecutiveActivityTimelineService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;

class ExecutiveActivityTool implements MrFoxToolContract
{
    public function __construct(
        private ?ExecutiveActivityTimelineService $timelineService = null
    ) {
        $this->timelineService = $timelineService ?? app(ExecutiveActivityTimelineService::class);
    }

    public function name(): string
    {
        return 'executive.activity';
    }

    public function description(): string
    {
        return 'Retrieve unified cross-module executive activity stream across sales, invoices, messages, tasks, automations, and approvals.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of activity events to retrieve (default 20, max 50)',
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
        $limit = min(max((int) ($input['limit'] ?? 20), 1), 50);
        $timeline = $this->timelineService->getTimeline($context->user, $context->workspace, $limit);

        $safeData = array_map(fn ($t) => $t->toArray(), $timeline);
        $summary = sprintf('Retrieved %d recent cross-system business activity events.', count($timeline));

        return ToolResult::success($safeData, $summary, []);
    }
}
