<?php

namespace HiddenLeaf\Hrm\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrExitClearance;
use HiddenLeaf\Hrm\Models\HrExitInterview;

class HrExitStatusTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr_exit_status';
    }

    public function description(): string
    {
        return 'Get employees with pending exit process or clearances';
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
        $exitingEmployees = HrEmployee::where('workspace_id', $wsId)
            ->whereNotNull('exit_date')
            ->where('status', 'active')
            ->get();

        $data = [];
        foreach ($exitingEmployees as $emp) {
            $pendingClearance = HrExitClearance::where('employee_id', $emp->id)
                ->where('status', 'pending')
                ->count();
            $interviewDone = HrExitInterview::where('employee_id', $emp->id)->exists();

            $data[] = [
                'employee_name' => $emp->name,
                'exit_date' => $emp->exit_date?->toDateString(),
                'clearance_pending_count' => $pendingClearance,
                'interview_done' => $interviewDone,
            ];
        }

        $summary = sprintf('%d employee(s) currently in exit process.', count($data));

        return ToolResult::success($data, $summary, [
            ['type' => 'hrm', 'label' => 'Exit Management', 'route' => '/hrm/exit'],
        ]);
    }
}
