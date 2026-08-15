<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CommunicationConversation;

class CommunicationsUnreadSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'communications.unread.summary';
    }

    public function description(): string
    {
        return 'Get executive breakdown of unread communications across all channels with urgency distribution.';
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
        $wsId = $context->getWorkspaceId();

        $unreadCount = CommunicationConversation::where('workspace_id', $wsId)->where('unread_count', '>', 0)->count();
        $urgentCount = CommunicationConversation::where('workspace_id', $wsId)->where('unread_count', '>', 0)->where('priority_score', '>=', 75)->count();

        $byProvider = CommunicationConversation::where('workspace_id', $wsId)
            ->where('unread_count', '>', 0)
            ->selectRaw('provider, count(*) as count')
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->all();

        $data = [
            'total_unread_threads' => $unreadCount,
            'urgent_unread_threads' => $urgentCount,
            'by_provider' => $byProvider,
        ];

        $summary = sprintf('Unified Inbox: %d unread thread(s) (%d urgent).', $unreadCount, $urgentCount);

        return ToolResult::success($data, $summary, [
            ['type' => 'communications', 'label' => 'Unified Communications Inbox', 'route' => '/inbox'],
        ]);
    }
}
