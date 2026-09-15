<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use HiddenLeaf\Hrm\Domain\HRM\OnboardingService;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrEmployeeOnboarding;
use HiddenLeaf\Hrm\Models\HrEmployeeOnboardingTask;
use HiddenLeaf\Hrm\Models\HrOnboardingTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmOnboardingController extends HrmBaseController
{
    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');

        return Inertia::render('HRM/Onboarding/Index', [
            'onboardings' => HrEmployeeOnboarding::forWorkspace($workspace->organization_id, $workspace->id)
                ->with(['employee', 'template'])
                ->latest()
                ->paginate(25),
            'templates' => HrOnboardingTemplate::forWorkspace($workspace->organization_id, $workspace->id)->with('tasks')->get(),
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('status', 'active')->get(),
        ]);
    }

    public function storeTemplate(Request $request, OnboardingService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        $service->createTemplate($workspace, $validated, $request->user());

        return back()->with('success', 'Onboarding template created.');
    }

    public function storeTask(Request $request, HrOnboardingTemplate $template, OnboardingService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($template, $workspace);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'due_offset_days' => 'nullable|integer',
            'assigned_role' => 'nullable|string',
            'requires_document' => 'nullable|boolean',
        ]);

        $service->addTaskToTemplate($template, $validated);

        return back()->with('success', 'Task added to template.');
    }

    public function startOnboarding(Request $request, OnboardingService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'template_id' => 'nullable|integer',
        ]);

        $employee = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($validated['employee_id']);
        $onboarding = $service->startOnboarding($employee, $validated['template_id'] ?? null, $request->user());

        return redirect()->route('hrm.onboarding.show', $onboarding->id)->with('success', 'Onboarding initiated.');
    }

    public function show(Request $request, HrEmployeeOnboarding $onboarding): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $this->assertTenant($onboarding, $workspace);

        return Inertia::render('HRM/Onboarding/Show', [
            'onboarding' => $onboarding->load(['employee', 'tasks', 'template']),
            'progress' => app(OnboardingService::class)->getProgress($onboarding),
        ]);
    }

    public function completeTask(Request $request, HrEmployeeOnboarding $onboarding, HrEmployeeOnboardingTask $task, OnboardingService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($onboarding, $workspace);

        $service->completeTask($task, $request->user(), $request->input('notes'));

        return back()->with('success', 'Task marked as completed.');
    }
}
