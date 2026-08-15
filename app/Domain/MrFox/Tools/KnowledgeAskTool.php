<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\Knowledge\KnowledgeSearchService;
use App\Domain\MrFox\RiskLevel;

class KnowledgeAskTool implements MrFoxToolContract
{
    public function __construct(private ?KnowledgeSearchService $searchService = null)
    {
        $this->searchService = $searchService ?? app(KnowledgeSearchService::class);
    }

    public function name(): string
    {
        return 'knowledge.ask';
    }

    public function description(): string
    {
        return 'Answer an operational or policy question using verified workspace documents with precise citations.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['question'],
            'properties' => [
                'question' => [
                    'type' => 'string',
                    'description' => 'The question regarding company policies, procedures, SOPs, or guides',
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
        $question = (string) ($input['question'] ?? '');
        $search = $this->searchService->search($context, $question, 3);
        $results = $search['results'];
        $evidence = array_map(fn ($ev) => $ev->toArray(), $search['evidence']);

        if (empty($results)) {
            return ToolResult::success([], "No matching company documentation found for question: '{$question}'.", []);
        }

        $summary = sprintf('Retrieved %d document citation(s) answering: "%s".', count($results), $question);

        return ToolResult::success($results, $summary, $evidence);
    }
}
