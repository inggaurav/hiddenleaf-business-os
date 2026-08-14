<?php

namespace App\Domain\HRM;

use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrPayslip;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrmDashboardService
{
    public function getMetrics(Workspace $workspace): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $totalEmployees = HrEmployee::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
        $activeEmployees = HrEmployee::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 'active')->count();

        $presentToday = HrAttendance::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->whereDate('attendance_date', $today)
            ->where('status', 'present')
            ->count();

        $absentToday = HrAttendance::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->whereDate('attendance_date', $today)
            ->where('status', 'absent')
            ->count();

        $absentYesterday = HrAttendance::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->whereDate('attendance_date', $yesterday)
            ->where('status', 'absent')
            ->count();

        $onLeaveToday = HrLeaveRequest::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('status', 'approved')
            ->where('starts_on', '<=', $today)
            ->where('ends_on', '>=', $today)
            ->count();

        $pendingLeaves = HrLeaveRequest::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('status', 'pending')
            ->count();

        $totalBranches = DB::table('hr_branches')->where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
        $totalDepartments = DB::table('hr_departments')->where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
        $totalDesignations = DB::table('hr_designations')->where('organization_id', $orgId)->where('workspace_id', $wsId)->count();

        $payrollMonth = (float) HrPayslip::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->whereMonth('period_end', now()->month)
            ->whereYear('period_end', now()->year)
            ->sum('net_pay');

        // Department distribution
        $departments = DB::table('hr_departments')
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->get();

        $departmentDistribution = [];
        foreach ($departments as $dept) {
            $count = HrEmployee::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where('department_id', $dept->id)
                ->count();
            $departmentDistribution[] = [
                'name' => $dept->name,
                'value' => $count,
            ];
        }

        // Recent Employees
        $recentEmployees = HrEmployee::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest('joined_at')
            ->limit(5)
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'name' => $emp->name,
                'employee_number' => $emp->employee_number,
                'email' => $emp->email,
                'joined_at' => $emp->joined_at,
                'status' => $emp->status,
                'basic_salary' => (float) $emp->basic_salary,
            ]);

        // Recent Leaves
        $recentLeaves = HrLeaveRequest::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->with(['employee', 'type'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($leave) => [
                'id' => $leave->id,
                'employee_name' => $leave->employee->name ?? 'Unknown',
                'leave_type' => $leave->type->name ?? 'General Leave',
                'starts_on' => $leave->starts_on,
                'ends_on' => $leave->ends_on,
                'days' => (float) $leave->days,
                'status' => $leave->status,
            ]);

        // Upcoming holidays
        $upcomingHolidays = DB::table('hr_holidays')
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->whereDate('holiday_date', '>=', $today)
            ->orderBy('holiday_date')
            ->limit(5)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name,
                'holiday_date' => $h->holiday_date,
                'is_optional' => (bool) ($h->is_optional ?? false),
            ]);

        return [
            'stats' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'present_today' => $presentToday,
                'absent_today' => $absentToday,
                'absent_yesterday' => $absentYesterday,
                'on_leave_today' => $onLeaveToday,
                'pending_leaves' => $pendingLeaves,
                'total_branches' => $totalBranches,
                'total_departments' => $totalDepartments,
                'total_designations' => $totalDesignations,
                'payroll_month' => $payrollMonth,
            ],
            'departmentDistribution' => $departmentDistribution,
            'recentEmployees' => $recentEmployees,
            'recentLeaves' => $recentLeaves,
            'upcomingHolidays' => $upcomingHolidays,
        ];
    }
}
