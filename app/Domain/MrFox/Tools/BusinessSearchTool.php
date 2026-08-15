<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\CommandCenter\Search\BusinessSearchService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;

class BusinessSearchTool implements MrFoxToolContract
{
    public function __construct(
        private ?BusinessSearchService $searchService = null
    ) {
        $this->searchService = $searchService ?? app(BusinessSearchService::class);
    }

    public function name(): string
    {
        return 'business.search';
    }

    public function description(): string
    {
        return 'Search across all accessible business records in the workspace including leads, invoices, messages, tasks, knowledge documents, automations, and missions.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query or keyword',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum results to return (default 10, max 30)',
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
        $query = (string) ($input['query'] ?? '');
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 30);

        $results = $this->searchService->search($context->user, $context->workspace, $query, $limit);
        $safeData = array_map(fn ($r) => $r->toArray(), $results);

        $summary = sprintf("Found %d business records matching '%s'.", count($results), $query);

        $evidence = array_map(fn ($r) => [
            'type' => $r->type,
            'id' => $r->id,
            'label' => $r->title,
            'route' => $r->route,
        ], $results);

        return ToolResult::success($safeData, $summary, $evidence);
    }
}
