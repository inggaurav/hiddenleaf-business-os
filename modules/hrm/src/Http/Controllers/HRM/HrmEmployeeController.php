<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Models\HrBranch;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrShift;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrmEmployeeController extends HrmBaseController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');

        $query = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id);
        if (! $this->isWorkspaceManager($request, $workspace)) {
            $query->where('user_id', $request->user()->id);
        }

        return Inertia::render('HRM/Employees/Index', [
            'employees' => $query->latest()->paginate(25),
            'branches' => HrBranch::forWorkspace($workspace->organization_id, $workspace->id)->get(),
            'departments' => HrDepartment::forWorkspace($workspace->organization_id, $workspace->id)->get(),
            'designations' => HrDesignation::forWorkspace($workspace->organization_id, $workspace->id)->get(),
            'shifts' => HrShift::forWorkspace($workspace->organization_id, $workspace->id)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'employee_number' => 'nullable|string|max:50',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'shift_id' => 'nullable|integer',
            'basic_salary' => 'nullable|numeric|min:0',
            'joined_at' => 'nullable|date',
            'employment_type' => 'nullable|string',
        ]);

        $employee = DB::transaction(function () use ($workspace, $validated, $request) {
            $emp = HrEmployee::create([
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'employee_number' => $validated['employee_number'] ?? 'EMP-' . strtoupper(bin2hex(random_bytes(3))),
                'branch_id' => $validated['branch_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'designation_id' => $validated['designation_id'] ?? null,
                'shift_id' => $validated['shift_id'] ?? null,
                'basic_salary' => $validated['basic_salary'] ?? 0,
                'joined_at' => $validated['joined_at'] ?? Carbon::today(),
                'employment_type' => $validated['employment_type'] ?? 'full_time',
                'status' => 'active',
            ]);

            $this->auditLogger->log(
                $request->user()->id,
                $workspace->organization_id,
                $workspace->id,
                'hrm.employee.created',
                'hr_employee',
                (string) $emp->id,
                ['name' => $emp->name, 'email' => $emp->email]
            );

            return $emp;
        });

        return back()->with('success', "Employee {$employee->name} added successfully.");
    }

    public function show(Request $request, HrEmployee $employee): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $this->assertTenant($employee, $workspace);

        return Inertia::render('HRM/Employees/Show', [
            'employee' => $employee->load(['onboarding', 'timesheets', 'disciplinaryCases', 'exitInterview', 'exitClearances']),
            'documents' => DB::table('hr_documents')->where('employee_id', $employee->id)->get(),
        ]);
    }

    public function update(Request $request, HrEmployee $employee): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'shift_id' => 'nullable|integer',
            'employment_type' => 'nullable|string',
            'probation_status' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'pincode' => 'nullable|string',
            'bio' => 'nullable|string',
            'linkedin_url' => 'nullable|string',
            'notice_period_days' => 'nullable|integer',
        ]);

        $employee->update($validated);

        $this->auditLogger->log(
            $request->user()->id,
            $workspace->organization_id,
            $workspace->id,
            'hrm.employee.updated',
            'hr_employee',
            (string) $employee->id,
            $validated
        );

        return back()->with('success', 'Employee profile updated.');
    }

    public function setSalary(Request $request, HrEmployee $employee): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $validated = $request->validate([
            'basic_salary' => 'required|numeric|min:0',
        ]);

        $employee->update(['basic_salary' => $validated['basic_salary']]);

        $this->auditLogger->log(
            $request->user()->id,
            $workspace->organization_id,
            $workspace->id,
            'hrm.employee.salary_updated',
            'hr_employee',
            (string) $employee->id,
            ['basic_salary' => $employee->basic_salary],
            critical: true
        );

        return back()->with('success', 'Salary updated.');
    }

    public function uploadAvatar(Request $request, HrEmployee $employee): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $request->validate(['avatar' => 'required|image|max:5120']);
        $path = $request->file('avatar')->store("avatars/{$workspace->id}", 'public');

        $employee->update(['avatar_path' => $path]);

        return back()->with('success', 'Avatar updated.');
    }

    public function uploadDocument(Request $request, HrEmployee $employee): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:10240',
        ]);

        $path = $request->file('file')->store("documents/{$workspace->id}/{$employee->id}", 'local');

        DB::table('hr_documents')->insert([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'employee_id' => $employee->id,
            'title' => $request->string('title'),
            'file_path' => $path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function downloadDocument(Request $request, HrEmployee $employee, $document): StreamedResponse
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $this->assertTenant($employee, $workspace);

        $doc = DB::table('hr_documents')
            ->where('id', $document)
            ->where('employee_id', $employee->id)
            ->where('workspace_id', $workspace->id)
            ->first();

        abort_unless($doc && Storage::disk('local')->exists($doc->file_path), 404);

        return Storage::disk('local')->download($doc->file_path, $doc->title);
    }

    public function terminate(Request $request, HrEmployee $employee): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $validated = $request->validate([
            'exit_reason' => 'required|string',
            'exit_date' => 'required|date',
        ]);

        $employee->update([
            'status' => 'terminated',
            'exit_reason' => $validated['exit_reason'],
            'exit_date' => $validated['exit_date'],
            'ended_at' => $validated['exit_date'],
        ]);

        $this->auditLogger->log(
            $request->user()->id,
            $workspace->organization_id,
            $workspace->id,
            'hrm.employee.terminated',
            'hr_employee',
            (string) $employee->id,
            ['reason' => $validated['exit_reason'], 'date' => $validated['exit_date']],
            critical: true
        );

        return back()->with('success', 'Employee terminated.');
    }

    public function appraisals(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');

        return Inertia::render('HRM/Appraisals/Index', [
            'appraisals' => DB::table('hr_appraisals')
                ->where('workspace_id', $workspace->id)
                ->latest()
                ->paginate(25),
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->get(['id', 'name']),
        ]);
    }

    public function storeAppraisal(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');

        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'rating' => 'required|numeric|min:1|max:5',
            'evaluation_period' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        DB::table('hr_appraisals')->insert([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'employee_id' => $validated['employee_id'],
            'rating' => $validated['rating'],
            'evaluation_period' => $validated['evaluation_period'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'evaluated_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Appraisal recorded.');
    }
}
