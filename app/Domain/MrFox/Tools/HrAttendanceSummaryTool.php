<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\HrAttendance;
use App\Models\HrEmployee;

class HrAttendanceSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr.attendance.summary';
    }

    public function description(): string
    {
        return 'Query today attendance metrics, present count, absent employees, and clock-in statuses.';
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

        $totalEmployees = HrEmployee::where('workspace_id', $wsId)->count();
        $todayAttendance = HrAttendance::where('workspace_id', $wsId)
            ->whereDate('date', today())
            ->get();

        $presentCount = $todayAttendance->where('status', 'present')->count();
        $absentCount = max(0, $totalEmployees - $presentCount);
        $attendanceRate = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100, 1) : 100;

        $data = [
            'total_employees' => $totalEmployees,
            'present_today' => $presentCount,
            'absent_today' => $absentCount,
            'attendance_rate_percent' => $attendanceRate,
        ];

        $summary = sprintf(
            'Today Attendance: %d of %d present (%s%% rate) | %d absent',
            $presentCount,
            $totalEmployees,
            $attendanceRate,
            $absentCount
        );

        return ToolResult::success($data, $summary, [
            ['type' => 'hrm', 'label' => 'Attendance Tracker', 'route' => '/hrm/attendances'],
        ]);
    }
}
