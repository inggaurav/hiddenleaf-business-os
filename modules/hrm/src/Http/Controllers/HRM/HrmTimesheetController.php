<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use Carbon\Carbon;
use HiddenLeaf\Hrm\Domain\HRM\TimesheetService;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrTimesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmTimesheetController extends HrmBaseController
{
    public function index(Request $request, TimesheetService $service): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $weekStart = $request->query('week_start', Carbon::now()->startOfWeek()->toDateString());
        $weekly = $employee ? $service->getWeeklySummary($employee, $weekStart) : ['days' => [], 'total_hours' => 0];

        return Inertia::render('HRM/Timesheets/Index', [
            'employee' => $employee,
            'weekStart' => $weekStart,
            'weekly' => $weekly,
            'recentTimesheets' => $employee
                ? HrTimesheet::where('employee_id', $employee->id)->latest('work_date')->limit(20)->get()
                : [],
        ]);
    }

    public function store(Request $request, TimesheetService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $validated = $request->validate([
            'work_date' => 'required|date',
            'hours' => 'required|numeric|min:0.25|max:24',
            'project_code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'employee_id' => 'nullable|integer',
        ]);

        $employeeId = $validated['employee_id'] ?? null;
        if (! $employeeId || ! $this->isWorkspaceManager($request, $workspace)) {
            $emp = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
            $employeeId = $emp->id;
        }

        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($employeeId);
        $service->submit($employee, $validated, $request->user());

        return back()->with('success', 'Timesheet logged.');
    }

    public function teamIndex(Request $request, TimesheetService $service): Response
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $from = $request->query('from', Carbon::now()->startOfWeek()->toDateString());
        $to = $request->query('to', Carbon::now()->endOfWeek()->toDateString());

        return Inertia::render('HRM/Timesheets/Team', [
            'from' => $from,
            'to' => $to,
            'summary' => $service->getTeamSummary($workspace, $from, $to),
            'pendingTimesheets' => HrTimesheet::where('workspace_id', $workspace->id)
                ->where('status', 'draft')
                ->with('employee')
                ->latest()
                ->get(),
        ]);
    }

    public function approve(Request $request, HrTimesheet $timesheet, TimesheetService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($timesheet, $workspace);

        $service->approve($timesheet, $request->user());

        return back()->with('success', 'Timesheet approved.');
    }

    public function reject(Request $request, HrTimesheet $timesheet, TimesheetService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($timesheet, $workspace);

        $validated = $request->validate(['reason' => 'required|string']);
        $service->reject($timesheet, $validated['reason'], $request->user());

        return back()->with('success', 'Timesheet rejected.');
    }
}
