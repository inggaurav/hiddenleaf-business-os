<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use HiddenLeaf\Hrm\Domain\HRM\DisciplinaryService;
use HiddenLeaf\Hrm\Models\HrDisciplinaryCase;
use HiddenLeaf\Hrm\Models\HrEmployee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmDisciplinaryController extends HrmBaseController
{
    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.manage');

        return Inertia::render('HRM/Disciplinary/Index', [
            'cases' => HrDisciplinaryCase::forWorkspace($workspace->organization_id, $workspace->id)
                ->with(['employee', 'handler'])
                ->latest()
                ->paginate(25),
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('status', 'active')->get(),
        ]);
    }

    public function store(Request $request, DisciplinaryService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'type' => 'required|string|max:255',
            'severity' => 'required|in:minor,moderate,major,critical',
            'incident_date' => 'required|date',
            'incident_description' => 'required|string',
            'handled_by' => 'nullable|integer',
        ]);

        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($validated['employee_id']);
        $case = $service->openCase($employee, $validated, $request->user());

        return redirect()->route('hrm.disciplinary.show', $case->id)->with('success', "Disciplinary case {$case->case_number} opened.");
    }

    public function show(Request $request, HrDisciplinaryCase $case): Response
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($case, $workspace);

        return Inertia::render('HRM/Disciplinary/Show', [
            'case' => $case->load(['employee', 'handler', 'creator']),
        ]);
    }

    public function recordAction(Request $request, HrDisciplinaryCase $case, DisciplinaryService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($case, $workspace);

        $validated = $request->validate([
            'action_taken' => 'required|string|max:255',
            'action_notes' => 'required|string',
        ]);

        $service->recordAction($case, $validated['action_taken'], $validated['action_notes'], $request->user());

        return back()->with('success', 'Disciplinary action recorded.');
    }

    public function close(Request $request, HrDisciplinaryCase $case, DisciplinaryService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($case, $workspace);

        $service->closeCase($case, $request->user());

        return back()->with('success', 'Disciplinary case closed.');
    }

    public function reopen(Request $request, HrDisciplinaryCase $case, DisciplinaryService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($case, $workspace);

        $service->reopenCase($case, $request->user());

        return back()->with('success', 'Disciplinary case reopened.');
    }
}
