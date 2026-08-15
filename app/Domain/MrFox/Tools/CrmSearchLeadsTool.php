<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmLead;

class CrmSearchLeadsTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'crm.search.leads';
    }

    public function description(): string
    {
        return 'Search and list CRM leads in the current workspace by name, email, company, or pipeline stage.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional search query for lead name, email, or company',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (default 10, max 50)',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'crm.manage';
    }

    public function requiredModule(): ?string
    {
        return 'crm';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $query = $input['query'] ?? null;
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 50);

        $builder = CrmLead::query()
            ->where('workspace_id', $wsId)
            ->with(['stage', 'pipeline']);

        if (! empty($query)) {
            $builder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('company', 'like', "%{$query}%");
            });
        }

        $leads = $builder->latest()->take($limit)->get();

        $safeLeads = $leads->map(fn ($lead) => [
            'id' => $lead->id,
            'name' => $lead->name,
            'company' => $lead->company,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'estimated_value' => (float) $lead->estimated_value,
            'stage' => $lead->stage?->name,
            'pipeline' => $lead->pipeline?->name,
            'status' => $lead->status,
        ]);

        $evidence = $leads->map(fn ($lead) => [
            'type' => 'lead',
            'id' => $lead->id,
            'label' => "{$lead->name} ({$lead->company})",
            'route' => "/crm/leads/{$lead->id}",
        ])->all();

        $summary = sprintf('Found %d CRM lead(s) matching criteria.', $leads->count());

        return ToolResult::success($safeLeads->toArray(), $summary, $evidence);
    }
}
