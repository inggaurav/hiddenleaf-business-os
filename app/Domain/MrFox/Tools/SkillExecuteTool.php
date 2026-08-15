<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\Review\ContentReviewEngine;
use App\Domain\MrFox\RiskLevel;
use App\Domain\MrFox\Skills\SkillRegistry;

class SkillExecuteTool implements MrFoxToolContract
{
    public function __construct(
        private ?SkillRegistry $skillRegistry = null,
        private ?ProviderRouter $providerRouter = null,
        private ?ContentReviewEngine $reviewEngine = null
    ) {
        $this->skillRegistry = $skillRegistry ?? app(SkillRegistry::class);
        $this->providerRouter = $providerRouter ?? app(ProviderRouter::class);
        $this->reviewEngine = $reviewEngine ?? app(ContentReviewEngine::class);
    }

    public function name(): string
    {
        return 'skill.execute';
    }

    public function description(): string
    {
        return 'Execute a specialist marketing or content skill (e.g. social_post, meta_ad, seo_blog, email_campaign) grounded in workspace Brand Profile.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['skill_id', 'parameters'],
            'properties' => [
                'skill_id' => [
                    'type' => 'string',
                    'description' => 'The identifier of the skill to execute (social_post, meta_ad, seo_blog, email_campaign, repurpose_content)',
                ],
                'parameters' => [
                    'type' => 'object',
                    'description' => 'The structured input parameters required by the target skill',
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
        $skillId = (string) ($input['skill_id'] ?? '');
        $parameters = is_array($input['parameters'] ?? null) ? $input['parameters'] : [];

        $skill = $this->skillRegistry->get($skillId);
        if (! $skill) {
            return ToolResult::error("Skill '{$skillId}' is not registered.");
        }

        // Build prompt from template
        $prompt = $skill->promptTemplate;
        foreach ($parameters as $k => $v) {
            $prompt = str_replace("{{$k}}", is_string($v) ? $v : json_encode($v), $prompt);
        }

        $provider = $this->providerRouter->resolve($context->workspace);
        $aiReq = new AiRequest(messages: [
            ['role' => 'user', 'content' => $prompt],
        ]);

        $aiRes = $provider->chat($aiReq);
        $generatedText = $aiRes->content;

        // Perform automated quality review
        $review = $this->reviewEngine->review($context, $generatedText);

        $data = [
            'skill_id' => $skillId,
            'skill_name' => $skill->name,
            'content' => $generatedText,
            'review_score' => $review['score'],
            'review_status' => $review['status'],
            'suggestions' => $review['suggestions'],
        ];

        $summary = "Executed skill '{$skill->name}' (Quality Score: {$review['score']}/100 - {$review['status']}).";

        return ToolResult::success($data, $summary, [
            ['type' => 'skills', 'label' => "Skill: {$skill->name}", 'route' => '/marketing-studio'],
        ]);
    }
}
