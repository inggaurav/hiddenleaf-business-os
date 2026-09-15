<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use HiddenLeaf\Hrm\Domain\HRM\TrainingService;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrTrainingEnrollment;
use HiddenLeaf\Hrm\Models\HrTrainingProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmTrainingController extends HrmBaseController
{
    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');

        return Inertia::render('HRM/Training/Index', [
            'programs' => HrTrainingProgram::forWorkspace($workspace->organization_id, $workspace->id)
                ->withCount('enrollments')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(Request $request, TrainingService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'mode' => 'nullable|string',
            'trainer_name' => 'nullable|string',
            'trainer_contact' => 'nullable|string',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date',
            'duration_hours' => 'nullable|integer',
            'cost_per_head' => 'nullable|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'location' => 'nullable|string',
        ]);

        $service->createProgram($workspace, $validated, $request->user());

        return back()->with('success', 'Training program created.');
    }

    public function show(Request $request, HrTrainingProgram $program, TrainingService $service): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $this->assertTenant($program, $workspace);

        return Inertia::render('HRM/Training/Show', [
            'program' => $program->load(['enrollments.employee']),
            'report' => $service->getProgramReport($program),
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('status', 'active')->get(),
        ]);
    }

    public function enroll(Request $request, HrTrainingProgram $program, TrainingService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($program, $workspace);

        $validated = $request->validate(['employee_id' => 'required|integer']);
        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($validated['employee_id']);

        $service->enroll($program, $employee, $request->user());

        return back()->with('success', 'Employee enrolled in training program.');
    }

    public function recordOutcome(Request $request, HrTrainingProgram $program, HrTrainingEnrollment $enrollment, TrainingService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($program, $workspace);

        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'passed' => 'required|boolean',
            'certificate' => 'nullable|file|max:10240',
        ]);

        $certPath = null;
        if ($request->hasFile('certificate')) {
            $certPath = $request->file('certificate')->store("certificates/{$workspace->id}", 'local');
        }

        $service->recordOutcome($enrollment, (float) $validated['score'], (bool) $validated['passed'], $certPath, $request->user());

        return back()->with('success', 'Training outcome recorded.');
    }
}
