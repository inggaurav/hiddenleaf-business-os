<?php

namespace HiddenLeaf\Hrm\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use HiddenLeaf\Hrm\Models\HrCandidate;
use HiddenLeaf\Hrm\Models\HrJobPosition;

class HrRecruitmentPipelineTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr_recruitment_pipeline';
    }

    public function description(): string
    {
        return 'Get open job positions and candidate counts per stage';
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
        return 'hrm.manage';
    }

    public function requiredModule(): ?string
    {
        return 'hrm';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $positions = HrJobPosition::where('workspace_id', $wsId)->where('status', 'open')->get();

        $data = [];
        $stages = ['applied', 'screening', 'interview', 'assessment', 'offer', 'hired', 'rejected'];

        foreach ($positions as $pos) {
            $pipeline = [];
            foreach ($stages as $stage) {
                $count = HrCandidate::where('job_position_id', $pos->id)->where('stage', $stage)->count();
                $pipeline[] = ['stage' => $stage, 'count' => $count];
            }
            $data[] = [
                'position_title' => $pos->title,
                'status' => $pos->status,
                'openings' => $pos->openings,
                'pipeline' => $pipeline,
            ];
        }

        $summary = sprintf('Found %d open positions in workspace recruitment pipeline.', count($data));

        return ToolResult::success($data, $summary, [
            ['type' => 'hrm', 'label' => 'Recruitment Pipeline', 'route' => '/hrm/recruitment'],
        ]);
    }
}
