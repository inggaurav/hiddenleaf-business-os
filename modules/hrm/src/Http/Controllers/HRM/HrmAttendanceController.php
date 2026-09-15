<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Models\HrAttendance;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrEmployee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmAttendanceController extends HrmBaseController
{
    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $date = $request->query('date', Carbon::today()->toDateString());

        $employees = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('status', 'active')
            ->get();

        $attendances = HrAttendance::where('workspace_id', $workspace->id)
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('employee_id');

        return Inertia::render('HRM/Attendance/Index', [
            'date' => $date,
            'employees' => $employees,
            'attendances' => $attendances,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');

        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'attendance_date' => 'required|date',
            'status' => 'required|in:present,absent,half_day,late',
            'clock_in' => 'nullable',
            'clock_out' => 'nullable',
        ]);

        HrAttendance::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'employee_id' => $validated['employee_id'],
                'attendance_date' => $validated['attendance_date'],
            ],
            [
                'organization_id' => $workspace->organization_id,
                'status' => $validated['status'],
                'clock_in' => $validated['clock_in'] ?? null,
                'clock_out' => $validated['clock_out'] ?? null,
            ]
        );

        return back()->with('success', 'Attendance recorded.');
    }

    public function selfMark(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $today = Carbon::today()->toDateString();
        $record = HrAttendance::where('workspace_id', $workspace->id)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if (! $record) {
            HrAttendance::create([
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'employee_id' => $employee->id,
                'attendance_date' => $today,
                'status' => 'present',
                'clock_in' => Carbon::now()->toTimeString(),
            ]);

            return back()->with('success', 'Clocked in successfully.');
        }

        if (! $record->clock_out) {
            $record->update(['clock_out' => Carbon::now()->toTimeString()]);

            return back()->with('success', 'Clocked out successfully.');
        }

        return back()->with('info', 'Attendance already marked for today.');
    }

    public function report(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);

        $attendances = HrAttendance::where('workspace_id', $workspace->id)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->get();

        return Inertia::render('HRM/Attendance/Report', [
            'month' => $month,
            'year' => $year,
            'attendances' => $attendances,
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->get(['id', 'name']),
        ]);
    }
}
