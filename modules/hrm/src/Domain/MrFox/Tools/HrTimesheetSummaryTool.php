<?php

namespace HiddenLeaf\Hrm\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrTimesheet;

class HrTimesheetSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr_timesheet_summary';
    }

    public function description(): string
    {
        return 'Get team timesheet hours for a date range';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                'to' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
            ],
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
        $from = $input['from'] ?? Carbon::now()->startOfWeek()->toDateString();
        $to = $input['to'] ?? Carbon::now()->endOfWeek()->toDateString();

        $employees = HrEmployee::where('workspace_id', $wsId)->where('status', 'active')->get();
        $data = [];

        foreach ($employees as $employee) {
            $timesheets = HrTimesheet::where('employee_id', $employee->id)
                ->whereBetween('work_date', [$from, $to])
                ->get();

            $total = (float) $timesheets->sum('hours');
            $approved = (float) $timesheets->where('status', 'approved')->sum('hours');
            $pending = (float) $timesheets->where('status', 'draft')->sum('hours');

            $data[] = [
                'employee_name' => $employee->name,
                'total_hours' => $total,
                'approved_hours' => $approved,
                'pending_hours' => $pending,
            ];
        }

        $summary = sprintf('Timesheet summary for %d employees from %s to %s.', count($data), $from, $to);

        return ToolResult::success($data, $summary, [
            ['type' => 'hrm', 'label' => 'Timesheets', 'route' => '/hrm/timesheets'],
        ]);
    }
}
