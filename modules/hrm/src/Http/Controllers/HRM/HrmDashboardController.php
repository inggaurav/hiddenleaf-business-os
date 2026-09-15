<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Models\HrAttendance;
use App\Models\HrLeaveRequest;
use App\Models\HrPayslip;
use HiddenLeaf\Hrm\Domain\HRM\HrmDashboardService;
use HiddenLeaf\Hrm\Models\HrEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HrmDashboardController extends HrmBaseController
{
    public function index(Request $request, HrmDashboardService $dashboardService): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');

        if (! $this->isWorkspaceManager($request, $workspace)) {
            $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $employee) {
                return Inertia::render('HRM/EmployeeDashboard', [
                    'employee' => null,
                    'attendance' => [],
                    'leaves' => [],
                    'payslips' => [],
                    'events' => [],
                    'policies' => [],
                ]);
            }

            return Inertia::render('HRM/EmployeeDashboard', [
                'employee' => $employee,
                'attendance' => HrAttendance::where('workspace_id', $workspace->id)
                    ->where('employee_id', $employee->id)
                    ->latest('attendance_date')
                    ->limit(10)
                    ->get(),
                'leaves' => HrLeaveRequest::where('workspace_id', $workspace->id)
                    ->where('employee_id', $employee->id)
                    ->with('type')
                    ->latest()
                    ->get(),
                'payslips' => HrPayslip::where('workspace_id', $workspace->id)
                    ->where('employee_id', $employee->id)
                    ->latest('period_end')
                    ->get(),
                'events' => DB::table('hr_employee_events')
                    ->where('workspace_id', $workspace->id)
                    ->where('employee_id', $employee->id)
                    ->latest('event_date')
                    ->get(),
                'policies' => DB::table('hr_policies')
                    ->where('workspace_id', $workspace->id)
                    ->where('status', 'active')
                    ->latest()
                    ->get(),
            ]);
        }

        $data = $dashboardService->getExtendedMetrics($workspace);

        return Inertia::render('HRM/Dashboard', [
            'metrics' => $data['stats'] ?? [],
            'extended' => $data['hrm_extended'] ?? [],
        ] + $data);
    }
}
