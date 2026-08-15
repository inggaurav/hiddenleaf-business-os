<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxBrandProfile;

class BrandProfileGetTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'brand_profile.get';
    }

    public function description(): string
    {
        return 'Retrieve active workspace Brand Profile including tone of voice, audience personas, positioning, and compliance rules.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'profile_id' => [
                    'type' => 'integer',
                    'description' => 'Optional specific brand profile ID. If omitted, returns the default active brand profile.',
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
        $wsId = $context->getWorkspaceId();
        $profileId = ! empty($input['profile_id']) ? (int) $input['profile_id'] : null;

        $builder = MrFoxBrandProfile::where('workspace_id', $wsId);
        if ($profileId) {
            $builder->where('id', $profileId);
        } else {
            $builder->orderByDesc('is_default')->latest();
        }

        $profile = $builder->first();

        if (! $profile) {
            return ToolResult::success([
                'name' => 'Default Company Brand',
                'tone_of_voice' => ['tone' => 'Professional, confident, helpful'],
                'compliance_guidelines' => ['prohibited_claims' => 'No unverifiable absolutes'],
            ], 'No custom brand profile configured; using default company tone.', []);
        }

        $safeData = [
            'id' => $profile->id,
            'name' => $profile->name,
            'industry' => $profile->industry,
            'tagline' => $profile->tagline,
            'mission' => $profile->mission,
            'tone_of_voice' => $profile->tone_of_voice,
            'target_audience' => $profile->target_audience,
            'value_propositions' => $profile->value_propositions,
            'compliance_guidelines' => $profile->compliance_guidelines,
        ];

        $summary = "Brand Profile: '{$profile->name}' ({$profile->industry}).";

        return ToolResult::success($safeData, $summary, [
            ['type' => 'brand_profile', 'id' => $profile->id, 'label' => "Brand: {$profile->name}", 'route' => '/settings/brand'],
        ]);
    }
}
