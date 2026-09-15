<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrTimesheet;
use HiddenLeaf\Kernel\Services\AuditLogger;

class TimesheetService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(HrEmployee $employee, array $data, User $actor): HrTimesheet
    {
        $hours = (float) ($data['hours'] ?? 0);
        abort_unless($hours > 0 && $hours <= 24, 422, 'Hours must be greater than 0 and less than or equal to 24.');

        $timesheet = HrTimesheet::create([
            'organization_id' => $employee->organization_id,
            'workspace_id' => $employee->workspace_id,
            'employee_id' => $employee->id,
            'work_date' => $data['work_date'],
            'project_code' => $data['project_code'] ?? null,
            'description' => $data['description'] ?? null,
            'hours' => $hours,
            'status' => 'draft',
        ]);

        $this->audit->log(
            $actor->id,
            $employee->organization_id,
            $employee->workspace_id,
            'timesheet.submitted',
            'hr_timesheet',
            (string) $timesheet->id,
            ['employee_id' => $employee->id, 'work_date' => $timesheet->work_date, 'hours' => $hours]
        );

        return $timesheet;
    }

    public function approve(HrTimesheet $timesheet, User $actor): HrTimesheet
    {
        $timesheet->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => Carbon::now(),
        ]);

        $this->audit->log(
            $actor->id,
            $timesheet->organization_id,
            $timesheet->workspace_id,
            'timesheet.approved',
            'hr_timesheet',
            (string) $timesheet->id,
            ['employee_id' => $timesheet->employee_id, 'hours' => $timesheet->hours]
        );

        return $timesheet;
    }

    public function reject(HrTimesheet $timesheet, string $reason, User $actor): HrTimesheet
    {
        $desc = trim(($timesheet->description ? $timesheet->description . ' | ' : '') . 'Rejected: ' . $reason);
        $timesheet->update([
            'status' => 'rejected',
            'description' => $desc,
        ]);

        $this->audit->log(
            $actor->id,
            $timesheet->organization_id,
            $timesheet->workspace_id,
            'timesheet.rejected',
            'hr_timesheet',
            (string) $timesheet->id,
            ['reason' => $reason]
        );

        return $timesheet;
    }

    public function getWeeklySummary(HrEmployee $employee, string $weekStart): array
    {
        $start = Carbon::parse($weekStart)->startOfDay();
        $days = [];
        $totalHours = 0.0;

        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $records = HrTimesheet::where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->get();

            $dayHours = (float) $records->sum('hours');
            $totalHours += $dayHours;
            $statuses = $records->pluck('status')->unique()->values()->all();

            $days[$date] = [
                'hours' => $dayHours,
                'status' => empty($statuses) ? 'none' : implode(',', $statuses),
            ];
        }

        return [
            'days' => $days,
            'total_hours' => $totalHours,
            'status' => $totalHours > 0 ? 'logged' : 'empty',
        ];
    }

    public function getTeamSummary(Workspace $ws, string $from, string $to): array
    {
        $employees = HrEmployee::forWorkspace($ws->organization_id, $ws->id)
            ->where('status', 'active')
            ->get();

        $summary = [];
        foreach ($employees as $employee) {
            $timesheets = HrTimesheet::where('employee_id', $employee->id)
                ->whereBetween('work_date', [$from, $to])
                ->get();

            $total = (float) $timesheets->sum('hours');
            $approved = (float) $timesheets->where('status', 'approved')->sum('hours');
            $pending = (float) $timesheets->where('status', 'draft')->sum('hours');

            $summary[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'total_hours' => $total,
                'approved_hours' => $approved,
                'pending_hours' => $pending,
            ];
        }

        return $summary;
    }
}
