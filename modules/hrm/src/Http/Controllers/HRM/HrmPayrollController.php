<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Domain\HRM\PayrollService;
use App\Models\HrPayslip;
use App\Models\HrSalaryComponent;
use HiddenLeaf\Hrm\Domain\HRM\PayrollRunService;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrPayrollRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HrmPayrollController extends HrmBaseController
{
    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');

        return Inertia::render('HRM/Payroll/Index', [
            'runs' => HrPayrollRun::forWorkspace($workspace->organization_id, $workspace->id)->latest('period_end')->paginate(15),
            'components' => HrSalaryComponent::forWorkspace($workspace->organization_id, $workspace->id)->get(),
            'recentPayslips' => HrPayslip::where('workspace_id', $workspace->id)->latest()->limit(15)->get(),
        ]);
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction',
            'calculation' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
        ]);

        HrSalaryComponent::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'calculation' => $validated['calculation'],
            'value' => $validated['value'],
        ]);

        return back()->with('success', 'Salary component created.');
    }

    public function assignComponent(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'salary_component_id' => 'required|integer',
            'value' => 'nullable|numeric|min:0',
        ]);

        DB::table('hr_employee_salary_components')->updateOrInsert(
            [
                'employee_id' => $validated['employee_id'],
                'salary_component_id' => $validated['salary_component_id'],
            ],
            [
                'value' => $validated['value'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('success', 'Salary component assigned.');
    }

    public function salaries(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $employees = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('status', 'active')
            ->paginate(30);

        return Inertia::render('HRM/Salaries', [
            'employees' => $employees,
        ]);
    }

    public function createRun(Request $request, PayrollRunService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $run = $service->createRun($workspace, $validated['period_start'], $validated['period_end'], $request->user());

        return redirect()->route('hrm.payroll.run.show', $run->id)->with('success', "Payroll run {$run->run_number} created.");
    }

    public function showRun(Request $request, HrPayrollRun $run): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $this->assertTenant($run, $workspace);

        $payslips = HrPayslip::where('payroll_run_id', $run->id)->with('lines')->get();

        return Inertia::render('HRM/Payroll/RunShow', [
            'run' => $run,
            'payslips' => $payslips,
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('status', 'active')->get(),
        ]);
    }

    public function addEmployee(Request $request, HrPayrollRun $run, PayrollRunService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($run, $workspace);

        $validated = $request->validate(['employee_id' => 'required|integer']);
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($validated['employee_id']);

        $service->addEmployee($run, $employee, $request->user());

        return back()->with('success', "Employee added to payroll run.");
    }

    public function addAllActive(Request $request, HrPayrollRun $run, PayrollRunService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($run, $workspace);

        $payslips = $service->addAllActive($run, $request->user());

        return back()->with('success', count($payslips) . " active employees added to payroll run.");
    }

    public function approveRun(Request $request, HrPayrollRun $run, PayrollRunService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($run, $workspace);

        $service->approve($run, $request->user());

        return back()->with('success', "Payroll run approved.");
    }

    public function payRun(Request $request, HrPayrollRun $run, PayrollRunService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($run, $workspace);

        $validated = $request->validate(['bank_account_id' => 'nullable|integer']);
        $service->pay($run, $request->user(), $validated['bank_account_id'] ?? null);

        return back()->with('success', "Payroll run disbursed successfully.");
    }

    public function generatePayslip(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($validated['employee_id']);
        app(PayrollService::class)->generate($employee, $validated['period_start'], $validated['period_end'], $request->user());

        return back()->with('success', 'Payslip generated.');
    }

    public function payPayslip(Request $request, $payslip): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $ps = HrPayslip::where('workspace_id', $workspace->id)->findOrFail($payslip);

        app(PayrollService::class)->pay($ps, $request->user());

        return back()->with('success', 'Payslip marked as paid.');
    }
}
