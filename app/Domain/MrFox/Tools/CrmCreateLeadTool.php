<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;

class CrmCreateLeadTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'crm.create.lead';
    }

    public function description(): string
    {
        return 'Create a new CRM lead with name, email, phone, company, and estimated value in the current workspace.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Full name of the prospective lead or contact',
                ],
                'email' => [
                    'type' => 'string',
                    'description' => 'Email address of the lead',
                ],
                'phone' => [
                    'type' => 'string',
                    'description' => 'Phone number of the lead',
                ],
                'company_name' => [
                    'type' => 'string',
                    'description' => 'Organization or company name',
                ],
                'value' => [
                    'type' => 'number',
                    'description' => 'Estimated commercial contract value',
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
        return RiskLevel::LOW;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $orgId = $context->getOrganizationId();
        $wsId = $context->getWorkspaceId();

        $pipeline = CrmPipeline::where('workspace_id', $wsId)->first();
        if (! $pipeline && $orgId) {
            $pipeline = CrmPipeline::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => 'Default Pipeline',
                'is_default' => true,
            ]);
        }

        $stage = $pipeline ? CrmStage::where('pipeline_id', $pipeline->id)->first() : null;
        if (! $stage && $pipeline) {
            $stage = CrmStage::create([
                'pipeline_id' => $pipeline->id,
                'name' => 'Inbound Lead',
                'position' => 0,
                'probability' => 20,
            ]);
        }

        $lead = CrmLead::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'pipeline_id' => $pipeline?->id ?? 1,
            'stage_id' => $stage?->id ?? 1,
            'created_by' => $context->user->id,
            'name' => $input['name'],
            'email' => $input['email'] ?? null,
            'phone' => $input['phone'] ?? null,
            'company' => $input['company_name'] ?? null,
            'estimated_value' => $input['value'] ?? 0,
            'status' => 'active',
        ]);

        $summary = "Successfully created new CRM Lead '{$lead->name}' (#{$lead->id}).";

        return ToolResult::success($lead->toArray(), $summary, [
            ['type' => 'lead', 'id' => $lead->id, 'label' => "Lead: {$lead->name}", 'route' => "/crm/leads/{$lead->id}"],
        ]);
    }
}
