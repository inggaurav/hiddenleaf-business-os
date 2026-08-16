<?php

namespace App\Http\Controllers;

use App\Domain\HRM\HrmDashboardService;
use App\Domain\HRM\PayrollService;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\HrPayslip;
use App\Models\HrSalaryComponent;
use App\Models\Workspace;
use Carbon\Carbon;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HrmController extends Controller
{
    public function dashboard(Request $request, HrmDashboardService $dashboardService)
    {
        $workspace = $this->workspace($request, 'hrm.view');
        if (! $this->isWorkspaceManager($request, $workspace)) {
            $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('user_id', $request->user()->id)->firstOrFail();

            return Inertia::render('HRM/EmployeeDashboard', [
                'employee' => $employee,
                'attendance' => HrAttendance::where('workspace_id', $workspace->id)->where('employee_id', $employee->id)->latest('attendance_date')->limit(10)->get(),
                'leaves' => HrLeaveRequest::where('workspace_id', $workspace->id)->where('employee_id', $employee->id)->with('type')->latest()->get(),
                'payslips' => HrPayslip::where('workspace_id', $workspace->id)->where('employee_id', $employee->id)->latest('period_end')->get(),
                'events' => DB::table('hr_employee_events')->where('workspace_id', $workspace->id)->where('employee_id', $employee->id)->latest('event_date')->get(),
                'policies' => DB::table('hr_policies')->where('workspace_id', $workspace->id)->where('status', 'active')->latest()->get(),
            ]);
        }
        $data = $dashboardService->getMetrics($workspace);

        return Inertia::render('HRM/Dashboard', ['metrics' => $data['stats']] + $data);
    }

    public function index(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.view');

        $employees = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id);
        if (! $this->isWorkspaceManager($request, $workspace)) {
            $employees->where('user_id', $request->user()->id);
        }
        return Inertia::render('HRM/Index', [
            'employees' => $employees->paginate(30),
            'attendanceToday' => HrAttendance::where('workspace_id', $workspace->id)->whereDate('attendance_date', today())->count(),
            'pendingLeaves' => HrLeaveRequest::where('workspace_id', $workspace->id)->where('status', 'pending')->count(),
            'payrollTotal' => HrPayslip::where('workspace_id', $workspace->id)->whereMonth('period_end', now()->month)->sum('net_pay'),
            'metrics' => [
                'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('status', 'active')->count(),
                'attendance_today' => HrAttendance::where('workspace_id', $workspace->id)->whereDate('attendance_date', today())->count(),
                'pending_leaves' => HrLeaveRequest::where('workspace_id', $workspace->id)->where('status', 'pending')->count(),
                'payroll_month' => (float) HrPayslip::where('workspace_id', $workspace->id)->whereMonth('period_end', now()->month)->whereYear('period_end', now()->year)->sum('net_pay'),
                'departments' => DB::table('hr_departments')->where('workspace_id', $workspace->id)->count(),
                'upcoming_holidays' => DB::table('hr_holidays')->where('workspace_id', $workspace->id)->whereDate('holiday_date', '>=', today())->count(),
            ],
        ]);
    }

    public function storeStructure(Request $request, string $resource)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $tables = ['branches' => 'hr_branches', 'departments' => 'hr_departments', 'designations' => 'hr_designations', 'shifts' => 'hr_shifts', 'holidays' => 'hr_holidays'];
        abort_unless(isset($tables[$resource]), 404);
        $rules = ['name' => ['required', 'string', 'max:255']];
        if ($resource === 'departments') {
            $rules['branch_id'] = ['nullable', 'integer'];
        }
        if ($resource === 'designations') {
            $rules['department_id'] = ['nullable', 'integer'];
        }
        if ($resource === 'shifts') {
            $rules['starts_at'] = ['required', 'date_format:H:i'];
            $rules['ends_at'] = ['required', 'date_format:H:i'];
            $rules['grace_minutes'] = ['nullable', 'integer', 'min:0'];
        }
        if ($resource === 'holidays') {
            $rules['holiday_date'] = ['required', 'date'];
            $rules['is_optional'] = ['boolean'];
        }
        $data = $request->validate($rules);
        DB::table($tables[$resource])->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'HR structure saved.');
    }

    public function storeEmployee(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['employee_number' => ['required', 'string', 'max:50', Rule::unique('hr_employees')->where('workspace_id', $workspace->id)], 'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'joined_at' => ['required', 'date'], 'basic_salary' => ['required', 'numeric', 'min:0'], 'branch_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer'], 'designation_id' => ['nullable', 'integer'], 'shift_id' => ['nullable', 'integer']]);
        HrEmployee::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'status' => 'active']);

        return back()->with('success', 'Employee created.');
    }

    public function attendance(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'attendance_date' => ['required', 'date'], 'clock_in' => ['nullable', 'date'], 'clock_out' => ['nullable', 'date', 'after:clock_in'], 'status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'remote'])], 'notes' => ['nullable', 'string']]);
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['employee_id']);
        HrAttendance::updateOrCreate(['employee_id' => $employee->id, 'attendance_date' => $data['attendance_date']], $data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id]);

        return back()->with('success', 'Attendance recorded.');
    }

    public function storeLeaveType(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'annual_allowance' => ['required', 'numeric', 'min:0'], 'is_paid' => ['boolean']]);
        HrLeaveType::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $data['name']], $data + ['organization_id' => $workspace->organization_id]);

        return back()->with('success', 'Leave type saved.');
    }

    public function requestLeave(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'leave_type_id' => ['required', 'integer'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'reason' => ['nullable', 'string']]);
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['employee_id']);
        $type = HrLeaveType::where('workspace_id', $workspace->id)->findOrFail($data['leave_type_id']);
        $days = Carbon::parse($data['starts_on'])->diffInDays(Carbon::parse($data['ends_on'])) + 1;
        $used = (float) HrLeaveRequest::where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('status', 'approved')->whereYear('starts_on', Carbon::parse($data['starts_on'])->year)->sum('days');
        abort_if($used + $days > (float) $type->annual_allowance, 422, 'Leave allowance exceeded.');
        HrLeaveRequest::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'days' => $days, 'status' => 'pending']);

        return back()->with('success', 'Leave requested.');
    }

    public function reviewLeave(Request $request, HrLeaveRequest $leave, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($leave, $workspace);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['nullable', 'string']]);
        abort_unless($leave->status === 'pending', 422, 'Leave request already reviewed.');
        DB::transaction(function () use ($leave, $data, $request, $workspace, $audit) {
            $leave->update(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'leave.'.$data['decision'], 'hr_leave_request', (string) $leave->id, critical: true);
        });

        return back()->with('success', 'Leave reviewed.');
    }

    public function storeComponent(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['name' => ['required', 'string'], 'type' => ['required', Rule::in(['earning', 'deduction'])], 'calculation' => ['required', Rule::in(['fixed', 'percentage'])], 'value' => ['required', 'numeric', 'min:0'], 'is_taxable' => ['boolean']]);
        HrSalaryComponent::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id]);

        return back()->with('success', 'Salary component saved.');
    }

    public function assignComponent(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'component_id' => ['required', 'integer'], 'value' => ['nullable', 'numeric', 'min:0']]);
        HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['employee_id']);
        HrSalaryComponent::where('workspace_id', $workspace->id)->findOrFail($data['component_id']);
        DB::table('hr_employee_salary_components')->updateOrInsert(['employee_id' => $data['employee_id'], 'salary_component_id' => $data['component_id']], ['value' => $data['value'] ?? null]);

        return back()->with('success', 'Salary component assigned.');
    }

    public function generatePayslip(Request $request, PayrollService $payroll)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start']]);
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['employee_id']);
        $payroll->generate($employee, $data['period_start'], $data['period_end'], $request->user());

        return back()->with('success', 'Payslip generated.');
    }

    public function payPayslip(Request $request, HrPayslip $payslip, PayrollService $payroll)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        abort_unless((int) $payslip->organization_id === (int) $workspace->organization_id && (int) $payslip->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate(['bank_account_id' => ['nullable', 'integer']]);

        $payroll->pay($payslip, $request->user(), $data['bank_account_id'] ?? null);

        return back()->with('success', 'Payslip marked as paid and posted.');
    }

    public function appraisal(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'review_date' => ['required', 'date'], 'rating' => ['required', 'integer', 'between:1,5'], 'feedback' => ['nullable', 'string']]);
        HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['employee_id']);
        DB::table('hr_appraisals')->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'reviewer_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Appraisal recorded.');
    }

    public function uploadDocument(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:255'], 'file' => ['required', 'file', 'max:10240'], 'expires_on' => ['nullable', 'date']]);
        HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['employee_id']);
        $disk = config('filesystems.default', 'local');
        $file = $request->file('file');
        $path = $file->storeAs('hr/'.$workspace->id.'/'.$data['employee_id'], Str::uuid().'.'.$file->getClientOriginalExtension(), $disk);
        DB::table('hr_documents')->insert(['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'employee_id' => $data['employee_id'], 'name' => $data['name'], 'storage_disk' => $disk, 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'expires_on' => $data['expires_on'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Employee document uploaded.');
    }

    public function downloadDocument(Request $request, int $document)
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $record = DB::table('hr_documents')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('id', $document)->first();
        abort_unless($record && Storage::disk($record->storage_disk)->exists($record->storage_path), 404);

        return Storage::disk($record->storage_disk)->download($record->storage_path, $record->name);
    }

    public function lifecycle(Request $request, string $kind)
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $kind = $this->eventKind($kind);

        $employees = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id);
        $events = DB::table('hr_employee_events as event')->leftJoin('hr_employees as employee', 'employee.id', '=', 'event.employee_id')->leftJoin('hr_event_types as event_type', 'event_type.id', '=', 'event.event_type_id')->where('event.workspace_id', $workspace->id)->where('event.kind', $kind);
        if (! $this->isWorkspaceManager($request, $workspace)) {
            $employee = (clone $employees)->where('user_id', $request->user()->id)->firstOrFail();
            $employees->whereKey($employee->id);
            $events->where('event.employee_id', $employee->id);
        }
        return Inertia::render('HRM/Lifecycle', [
            'kind' => $kind,
            'canManage' => $this->isWorkspaceManager($request, $workspace),
            'employees' => $employees->orderBy('name')->get(['id', 'name', 'employee_number', 'basic_salary']),
            'types' => DB::table('hr_event_types')->where('workspace_id', $workspace->id)->where('kind', $kind)->orderBy('name')->get(),
            'events' => $events->select('event.*', 'employee.name as employee_name', 'event_type.name as type_name')->latest('event.event_date')->paginate(30),
        ]);
    }

    public function storeEventType(Request $request, string $kind)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $kind = $this->eventKind($kind);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        DB::table('hr_event_types')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'kind' => $kind, 'name' => $data['name']],
            $data + ['organization_id' => $workspace->organization_id, 'created_at' => now(), 'updated_at' => now()]
        );

        return back()->with('success', ucfirst($kind).' type saved.');
    }

    public function storeEvent(Request $request, string $kind)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $kind = $this->eventKind($kind);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'event_type_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['required', 'date'],
            'effective_date' => ['nullable', 'date'],
        ]);
        HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['employee_id']);
        if ($data['event_type_id'] ?? null) {
            abort_unless(DB::table('hr_event_types')->where('workspace_id', $workspace->id)->where('kind', $kind)->where('id', $data['event_type_id'])->exists(), 422);
        }
        DB::table('hr_employee_events')->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'kind' => $kind, 'status' => 'pending', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', ucfirst($kind).' record created.');
    }

    public function reviewEvent(Request $request, int $event)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'acknowledged', 'approved', 'rejected', 'resolved', 'completed'])], 'response' => ['nullable', 'string', 'max:5000']]);
        $updated = DB::table('hr_employee_events')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('id', $event)->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
        abort_unless($updated, 404);

        return back()->with('success', 'HR record reviewed.');
    }

    public function setSalary(Request $request, HrEmployee $employee)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);
        $data = $request->validate(['basic_salary' => ['required', 'numeric', 'min:0']]);
        $employee->update($data);

        return back()->with('success', 'Employee salary updated.');
    }

    public function salaries(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');

        return Inertia::render('HRM/Salaries', [
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->orderBy('name')->get(['id', 'employee_number', 'name', 'basic_salary', 'status']),
        ]);
    }

    public function leaveBalances(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $employees = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->orderBy('name')->get();
        if (! $this->isWorkspaceManager($request, $workspace)) {
            $employees = $employees->where('user_id', $request->user()->id)->values();
        }
        $types = HrLeaveType::where('workspace_id', $workspace->id)->orderBy('name')->get();
        $used = HrLeaveRequest::where('workspace_id', $workspace->id)->where('status', 'approved')->select('employee_id', 'leave_type_id', DB::raw('SUM(days) as used'))->groupBy('employee_id', 'leave_type_id')->get()->keyBy(fn ($row) => $row->employee_id.':'.$row->leave_type_id);

        return Inertia::render('HRM/LeaveBalances', ['employees' => $employees, 'leaveTypes' => $types, 'used' => $used]);
    }

    public function communications(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('user_id', $request->user()->id)->first();

        return Inertia::render('HRM/Communications', [
            'announcements' => DB::table('hr_announcements')->where('workspace_id', $workspace->id)->latest()->get(),
            'policies' => DB::table('hr_policies')->where('workspace_id', $workspace->id)->latest()->get(),
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->orderBy('name')->get(['id', 'name']),
            'canManage' => $this->isWorkspaceManager($request, $workspace),
            'acknowledgedPolicyIds' => $employee
                ? DB::table('hr_policy_acknowledgements')->where('employee_id', $employee->id)->pluck('policy_id')
                : [],
        ]);
    }

    public function storeAnnouncement(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'status' => ['required', Rule::in(['draft', 'published', 'archived'])]]);
        DB::table('hr_announcements')->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Announcement saved.');
    }

    public function storePolicy(Request $request)
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'version' => ['required', 'string', 'max:30'], 'content' => ['required', 'string'], 'effective_on' => ['nullable', 'date'], 'requires_acknowledgement' => ['boolean'], 'status' => ['required', Rule::in(['draft', 'active', 'archived'])]]);
        DB::table('hr_policies')->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Company policy saved.');
    }

    public function acknowledgePolicy(Request $request, int $policy)
    {
        $workspace = $this->workspace($request, 'hrm.view');
        abort_unless(DB::table('hr_policies')->where('workspace_id', $workspace->id)->where('id', $policy)->exists(), 404);
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('user_id', $request->user()->id)->firstOrFail();
        DB::table('hr_policy_acknowledgements')->updateOrInsert(['policy_id' => $policy, 'employee_id' => $employee->id], ['acknowledged_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Policy acknowledged.');
    }

    private function eventKind(string $kind): string
    {
        $allowed = ['award', 'promotion', 'resignation', 'termination', 'warning', 'complaint', 'transfer', 'acknowledgement', 'event'];
        abort_unless(in_array($kind, $allowed, true), 404);

        return $kind;
    }

    private function isWorkspaceManager(Request $request, Workspace $workspace): bool
    {
        return $request->user()->canInWorkspace('hrm.manage', $workspace);
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }

    private function assertTenant($model, Workspace $workspace): void
    {
        abort_unless((int) $model->organization_id === (int) $workspace->organization_id && (int) $model->workspace_id === (int) $workspace->id, 404);
    }
}
