<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Domain\MrFox\Skills\SkillRegistry;

class SkillListTool implements MrFoxToolContract
{
    public function __construct(private ?SkillRegistry $skillRegistry = null)
    {
        $this->skillRegistry = $skillRegistry ?? app(SkillRegistry::class);
    }

    public function name(): string
    {
        return 'skill.list';
    }

    public function description(): string
    {
        return 'List all available AI specialist content, copywriting, marketing, and SEO generation skills.';
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
        $skills = array_values(array_map(fn ($s) => $s->toArray(), $this->skillRegistry->all()));

        $summary = sprintf('Available AI Specialist Skills (%d registered).', count($skills));

        return ToolResult::success($skills, $summary, [
            ['type' => 'skills', 'label' => 'Marketing Studio Skills', 'route' => '/marketing-studio'],
        ]);
    }
}
