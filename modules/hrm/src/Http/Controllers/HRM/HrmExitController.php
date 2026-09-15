<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use Carbon\Carbon;
use HiddenLeaf\Hrm\Domain\HRM\ExitService;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrExitClearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmExitController extends HrmBaseController
{
    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.manage');

        $exits = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
            ->where(function ($q) {
                $q->whereNotNull('exit_date')->orWhere('status', 'terminated');
            })
            ->with(['exitInterview', 'exitClearances'])
            ->latest()
            ->paginate(25);

        return Inertia::render('HRM/Exit/Index', [
            'exits' => $exits,
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('status', 'active')->get(),
        ]);
    }

    public function initiate(Request $request, ExitService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'exit_reason' => 'required|string',
            'exit_date' => 'required|date',
        ]);

        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($validated['employee_id']);
        $service->initiateExit($employee, $validated['exit_reason'], Carbon::parse($validated['exit_date']), $request->user());

        return back()->with('success', 'Employee exit initiated.');
    }

    public function conductInterview(Request $request, HrEmployee $employee, ExitService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $validated = $request->validate([
            'interview_date' => 'nullable|date',
            'exit_reason' => 'nullable|string',
            'would_return' => 'nullable|string',
            'likes_about_company' => 'nullable|string',
            'dislikes_about_company' => 'nullable|string',
            'suggestions' => 'nullable|string',
            'overall_rating' => 'nullable|integer|min:1|max:5',
        ]);

        $service->conductExitInterview($employee, $validated, $request->user());

        return back()->with('success', 'Exit interview recorded.');
    }

    public function clearanceIndex(Request $request, HrEmployee $employee): Response
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        return Inertia::render('HRM/Exit/Clearance', [
            'employee' => $employee,
            'clearances' => HrExitClearance::where('employee_id', $employee->id)->get(),
            'isFullyCleared' => app(ExitService::class)->isFullyCleared($employee),
        ]);
    }

    public function addClearanceItems(Request $request, HrEmployee $employee, ExitService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.department' => 'required|string',
            'items.*.clearance_item' => 'required|string',
        ]);

        $service->addClearanceItems($employee, $validated['items'], $request->user());

        return back()->with('success', 'Clearance checklist items added.');
    }

    public function clearItem(Request $request, HrEmployee $employee, HrExitClearance $clearance, ExitService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);
        abort_unless($clearance->employee_id === $employee->id, 404);

        $service->clearItem($clearance, $request->user(), $request->input('remarks'));

        return back()->with('success', 'Clearance item cleared.');
    }

    public function completeExit(Request $request, HrEmployee $employee, ExitService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($employee, $workspace);

        $service->completeExit($employee, $request->user());

        return back()->with('success', "Exit completed for {$employee->name}.");
    }
}
