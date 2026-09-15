<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Models\HrDepartment;
use HiddenLeaf\Hrm\Domain\HRM\RecruitmentService;
use HiddenLeaf\Hrm\Models\HrCandidate;
use HiddenLeaf\Hrm\Models\HrCandidateInterview;
use HiddenLeaf\Hrm\Models\HrJobPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmRecruitmentController extends HrmBaseController
{
    public function index(Request $request, RecruitmentService $service): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $positionId = $request->query('position_id');

        $positions = HrJobPosition::forWorkspace($workspace->organization_id, $workspace->id)->latest()->get();
        $candidatesQuery = HrCandidate::forWorkspace($workspace->organization_id, $workspace->id)->with(['position', 'interviews']);

        if ($positionId) {
            $candidatesQuery->where('job_position_id', $positionId);
        }

        return Inertia::render('HRM/Recruitment/Index', [
            'positions' => $positions,
            'candidates' => $candidatesQuery->latest()->paginate(25),
            'pipelineSummary' => $service->getPipelineSummary($workspace, $positionId ? (int) $positionId : null),
            'departments' => HrDepartment::forWorkspace($workspace->organization_id, $workspace->id)->get(),
        ]);
    }

    public function storePosition(Request $request, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'employment_type' => 'nullable|string',
            'location' => 'nullable|string',
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0',
            'openings' => 'nullable|integer|min:1',
            'closes_on' => 'nullable|date',
        ]);

        $service->createPosition($workspace, $validated, $request->user());

        return back()->with('success', 'Job position posted.');
    }

    public function closePosition(Request $request, HrJobPosition $position, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($position, $workspace);

        $service->closePosition($position, $request->user());

        return back()->with('success', 'Job position closed.');
    }

    public function storeCandidate(Request $request, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'job_position_id' => 'nullable|integer',
            'source' => 'nullable|string',
            'current_company' => 'nullable|string',
            'current_designation' => 'nullable|string',
            'current_salary' => 'nullable|numeric|min:0',
            'expected_salary' => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer',
            'available_from' => 'nullable|date',
            'notes' => 'nullable|string',
            'resume' => 'nullable|file|max:10240',
        ]);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store("resumes/{$workspace->id}", 'local');
        }

        $service->addCandidate($workspace, $validated, $resumePath, $request->user());

        return back()->with('success', 'Candidate registered.');
    }

    public function showCandidate(Request $request, HrCandidate $candidate): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $this->assertTenant($candidate, $workspace);

        return Inertia::render('HRM/Recruitment/CandidateShow', [
            'candidate' => $candidate->load(['position', 'interviews.interviewer', 'assignedUser']),
        ]);
    }

    public function moveStage(Request $request, HrCandidate $candidate, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($candidate, $workspace);

        $validated = $request->validate([
            'stage' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $service->moveStage($candidate, $validated['stage'], $validated['notes'] ?? null, $request->user());

        return back()->with('success', "Candidate moved to {$validated['stage']}.");
    }

    public function rejectCandidate(Request $request, HrCandidate $candidate, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($candidate, $workspace);

        $validated = $request->validate(['reason' => 'required|string']);
        $service->rejectCandidate($candidate, $validated['reason'], $request->user());

        return back()->with('success', 'Candidate rejected.');
    }

    public function convertToEmployee(Request $request, HrCandidate $candidate, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($candidate, $workspace);

        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'shift_id' => 'nullable|integer',
            'employee_number' => 'nullable|string|max:50',
            'joined_at' => 'nullable|date',
            'basic_salary' => 'nullable|numeric|min:0',
        ]);

        $employee = $service->convertToEmployee($candidate, $validated, $request->user());

        return redirect()->route('hrm.employees.show', $employee->id)->with('success', "Candidate converted to employee {$employee->name}.");
    }

    public function scheduleInterview(Request $request, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'candidate_id' => 'required|integer',
            'round_name' => 'required|string|max:255',
            'interview_type' => 'nullable|string',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15',
            'location_or_link' => 'nullable|string',
            'interviewer_id' => 'nullable|integer',
        ]);

        $candidate = HrCandidate::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($validated['candidate_id']);
        $service->scheduleInterview($candidate, $validated, $request->user());

        return back()->with('success', 'Interview scheduled.');
    }

    public function recordOutcome(Request $request, HrCandidateInterview $interview, RecruitmentService $service): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $this->assertTenant($interview, $workspace);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'decision' => 'required|in:proceed,reject,hold',
            'feedback' => 'required|string',
        ]);

        $service->recordInterviewOutcome($interview, $validated['rating'], $validated['decision'], $validated['feedback'], $request->user());

        return back()->with('success', 'Interview outcome recorded.');
    }
}
