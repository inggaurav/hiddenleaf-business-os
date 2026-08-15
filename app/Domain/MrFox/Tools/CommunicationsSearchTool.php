<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CommunicationConversation;

class CommunicationsSearchTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'communications.search';
    }

    public function description(): string
    {
        return 'Search cross-channel unified communications (Gmail, WhatsApp, Slack, Social, Internal) by contact, subject, or message keyword.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Keyword or contact name/email to search'],
                'provider' => ['type' => 'string', 'description' => 'Optional filter by channel (gmail, whatsapp, slack, facebook, instagram, internal)'],
                'status' => ['type' => 'string', 'description' => 'Filter by status: open, in_progress, resolved, closed'],
                'limit' => ['type' => 'integer', 'description' => 'Maximum records to return (default: 10)'],
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
        $query = (string) ($input['query'] ?? '');
        $provider = $input['provider'] ?? null;
        $status = $input['status'] ?? null;
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 30);

        $builder = CommunicationConversation::where('workspace_id', $wsId);

        if ($provider) {
            $builder->where('provider', $provider);
        }
        if ($status) {
            $builder->where('status', $status);
        }
        if (! empty($query)) {
            $builder->where(function ($q) use ($query) {
                $q->where('participant_name', 'like', "%{$query}%")
                    ->orWhere('participant_identifier', 'like', "%{$query}%")
                    ->orWhere('subject', 'like', "%{$query}%")
                    ->orWhere('last_message_preview', 'like', "%{$query}%");
            });
        }

        $conversations = $builder->latest('last_message_at')->take($limit)->get();

        $data = $conversations->map(fn ($c) => [
            'id' => $c->id,
            'provider' => $c->provider,
            'subject' => $c->subject,
            'participant_name' => $c->participant_name,
            'participant_identifier' => $c->participant_identifier,
            'last_message' => $c->last_message_preview,
            'unread_count' => $c->unread_count,
            'priority_score' => $c->priority_score,
            'status' => $c->status,
        ])->all();

        $evidence = $conversations->map(fn ($c) => [
            'type' => 'communication_message',
            'id' => $c->id,
            'label' => "{$c->provider}: {$c->participant_name}",
            'route' => '/inbox',
        ])->all();

        $summary = sprintf('Found %d unified communication conversation(s).', count($data));

        return ToolResult::success($data, $summary, $evidence);
    }
}
