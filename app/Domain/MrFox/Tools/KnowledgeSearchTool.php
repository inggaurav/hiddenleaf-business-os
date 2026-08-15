<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\Knowledge\KnowledgeSearchService;
use App\Domain\MrFox\RiskLevel;

class KnowledgeSearchTool implements MrFoxToolContract
{
    public function __construct(private ?KnowledgeSearchService $searchService = null)
    {
        $this->searchService = $searchService ?? app(KnowledgeSearchService::class);
    }

    public function name(): string
    {
        return 'knowledge.search';
    }

    public function description(): string
    {
        return 'Search workspace knowledge base, policies, SOPs, uploaded manuals, and organizational documents.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The semantic topic, keyword, or question to search within knowledge documents',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of relevant excerpts to return (default: 5)',
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
        $limit = (int) ($input['limit'] ?? 5);

        $search = $this->searchService->search($context, $query, $limit);
        $results = $search['results'];
        $evidence = array_map(fn ($ev) => $ev->toArray(), $search['evidence']);

        $summary = sprintf('Found %d relevant knowledge document excerpt(s) for query "%s".', count($results), $query);

        return ToolResult::success($results, $summary, $evidence);
    }
}
