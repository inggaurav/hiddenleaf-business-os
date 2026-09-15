<?php

namespace HiddenLeaf\Hrm\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use HiddenLeaf\Hrm\Models\HrTrainingProgram;

class HrTrainingSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr_training_summary';
    }

    public function description(): string
    {
        return 'Get active training programs and enrollment status';
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
        $programs = HrTrainingProgram::where('workspace_id', $wsId)
            ->whereIn('status', ['planned', 'in_progress'])
            ->get();

        $data = [];
        foreach ($programs as $prog) {
            $enrolled = $prog->enrollments()->count();
            $passed = $prog->enrollments()->where('passed', true)->count();
            $completionRate = $enrolled > 0 ? round(($passed / $enrolled) * 100, 1) : 0.0;

            $data[] = [
                'program_title' => $prog->title,
                'status' => $prog->status,
                'enrolled_count' => $enrolled,
                'passed_count' => $passed,
                'completion_rate' => $completionRate,
            ];
        }

        $summary = sprintf('%d active training programs tracked.', count($data));

        return ToolResult::success($data, $summary, [
            ['type' => 'hrm', 'label' => 'Training Programs', 'route' => '/hrm/training'],
        ]);
    }
}
