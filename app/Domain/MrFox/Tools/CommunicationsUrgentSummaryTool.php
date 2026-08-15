<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CommunicationConversation;

class CommunicationsUrgentSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'communications.urgent.summary';
    }

    public function description(): string
    {
        return 'Retrieve list of high-priority customer interactions requiring immediate executive or team response.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'min_score' => ['type' => 'integer', 'description' => 'Minimum priority score (default: 75)'],
                'limit' => ['type' => 'integer', 'description' => 'Maximum records to return (default: 5)'],
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
        $wsId = $context->getWorkspaceId();
        $minScore = (int) ($input['min_score'] ?? 75);
        $limit = min(max((int) ($input['limit'] ?? 5), 1), 20);

        $urgentList = CommunicationConversation::where('workspace_id', $wsId)
            ->where('status', '!=', 'closed')
            ->where('priority_score', '>=', $minScore)
            ->orderByDesc('priority_score')
            ->latest('last_message_at')
            ->take($limit)
            ->get();

        $data = $urgentList->map(fn ($c) => [
            'id' => $c->id,
            'provider' => $c->provider,
            'contact' => $c->participant_name,
            'identifier' => $c->participant_identifier,
            'priority_score' => $c->priority_score,
            'intent' => $c->intent,
            'sentiment' => $c->sentiment,
            'last_message' => $c->last_message_preview,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
        ])->all();

        $evidence = $urgentList->map(fn ($c) => [
            'type' => 'communication_message',
            'id' => $c->id,
            'label' => "Urgent ({$c->priority_score}): {$c->participant_name}",
            'route' => '/inbox',
        ])->all();

        $summary = sprintf('Identified %d urgent communication thread(s) requiring attention.', count($data));

        return ToolResult::success($data, $summary, $evidence);
    }
}
