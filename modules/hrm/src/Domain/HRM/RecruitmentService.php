<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrCandidate;
use HiddenLeaf\Hrm\Models\HrCandidateInterview;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrJobPosition;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class RecruitmentService
{
    private const STAGES = [
        'applied' => ['screening', 'rejected'],
        'screening' => ['interview', 'rejected'],
        'interview' => ['assessment', 'rejected'],
        'assessment' => ['offer', 'rejected'],
        'offer' => ['hired', 'rejected'],
        'hired' => ['rejected'],
        'rejected' => [],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function createPosition(Workspace $ws, array $data, User $actor): HrJobPosition
    {
        $position = HrJobPosition::create([
            'organization_id' => $ws->organization_id,
            'workspace_id' => $ws->id,
            'department_id' => $data['department_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'requirements' => $data['requirements'] ?? null,
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'location' => $data['location'] ?? null,
            'salary_min' => $data['salary_min'] ?? null,
            'salary_max' => $data['salary_max'] ?? null,
            'openings' => $data['openings'] ?? 1,
            'status' => 'open',
            'posted_on' => $data['posted_on'] ?? Carbon::today(),
            'closes_on' => $data['closes_on'] ?? null,
            'hiring_manager_id' => $data['hiring_manager_id'] ?? $actor->id,
        ]);

        $this->audit->log(
            $actor->id,
            $ws->organization_id,
            $ws->id,
            'recruitment.position.created',
            'hr_job_position',
            (string) $position->id,
            ['title' => $position->title]
        );

        return $position;
    }

    public function closePosition(HrJobPosition $position, User $actor): HrJobPosition
    {
        $position->update(['status' => 'closed', 'closes_on' => Carbon::today()]);

        $this->audit->log(
            $actor->id,
            $position->organization_id,
            $position->workspace_id,
            'recruitment.position.closed',
            'hr_job_position',
            (string) $position->id,
            ['title' => $position->title]
        );

        return $position;
    }

    public function addCandidate(Workspace $ws, array $data, ?string $resumePath, User $actor): HrCandidate
    {
        $candidate = HrCandidate::create([
            'organization_id' => $ws->organization_id,
            'workspace_id' => $ws->id,
            'job_position_id' => $data['job_position_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'resume_path' => $resumePath,
            'source' => $data['source'] ?? 'direct',
            'current_company' => $data['current_company'] ?? null,
            'current_designation' => $data['current_designation'] ?? null,
            'current_salary' => $data['current_salary'] ?? null,
            'expected_salary' => $data['expected_salary'] ?? null,
            'notice_period_days' => $data['notice_period_days'] ?? null,
            'available_from' => $data['available_from'] ?? null,
            'stage' => 'applied',
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            $actor->id,
            $ws->organization_id,
            $ws->id,
            'recruitment.candidate.added',
            'hr_candidate',
            (string) $candidate->id,
            ['name' => $candidate->name, 'email' => $candidate->email]
        );

        return $candidate;
    }

    public function moveStage(HrCandidate $candidate, string $newStage, ?string $notes, User $actor): HrCandidate
    {
        $allowed = self::STAGES[$candidate->stage] ?? [];
        abort_unless(in_array($newStage, $allowed, true), 422, "Invalid stage transition from {$candidate->stage} to {$newStage}.");

        $updates = ['stage' => $newStage];
        if ($notes !== null) {
            $updates['notes'] = $notes;
        }
        if ($newStage === 'rejected') {
            $updates['status'] = 'rejected';
        }

        $candidate->update($updates);

        $this->audit->log(
            $actor->id,
            $candidate->organization_id,
            $candidate->workspace_id,
            'recruitment.candidate.stage_changed',
            'hr_candidate',
            (string) $candidate->id,
            ['from_stage' => $candidate->getOriginal('stage'), 'to_stage' => $newStage]
        );

        return $candidate;
    }

    public function rejectCandidate(HrCandidate $candidate, string $reason, User $actor): HrCandidate
    {
        $notes = trim(($candidate->notes ? $candidate->notes . "
" : '') . 'Rejection reason: ' . $reason);
        $candidate->update([
            'stage' => 'rejected',
            'status' => 'rejected',
            'notes' => $notes,
        ]);

        $this->audit->log(
            $actor->id,
            $candidate->organization_id,
            $candidate->workspace_id,
            'recruitment.candidate.rejected',
            'hr_candidate',
            (string) $candidate->id,
            ['reason' => $reason]
        );

        return $candidate;
    }

    public function scheduleInterview(HrCandidate $candidate, array $data, User $actor): HrCandidateInterview
    {
        $interview = HrCandidateInterview::create([
            'organization_id' => $candidate->organization_id,
            'workspace_id' => $candidate->workspace_id,
            'candidate_id' => $candidate->id,
            'round_name' => $data['round_name'],
            'interview_type' => $data['interview_type'] ?? 'in_person',
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'location_or_link' => $data['location_or_link'] ?? null,
            'status' => 'scheduled',
            'interviewer_id' => $data['interviewer_id'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            $actor->id,
            $candidate->organization_id,
            $candidate->workspace_id,
            'recruitment.interview.scheduled',
            'hr_candidate_interview',
            (string) $interview->id,
            ['candidate_id' => $candidate->id, 'round_name' => $interview->round_name]
        );

        return $interview;
    }

    public function recordInterviewOutcome(HrCandidateInterview $interview, int $rating, string $decision, string $feedback, User $actor): HrCandidateInterview
    {
        abort_unless($rating >= 1 && $rating <= 5, 422, 'Rating must be between 1 and 5.');
        abort_unless(in_array($decision, ['proceed', 'reject', 'hold'], true), 422, 'Invalid interview decision.');

        $interview->update([
            'rating' => $rating,
            'decision' => $decision,
            'feedback' => $feedback,
            'status' => 'completed',
        ]);

        $this->audit->log(
            $actor->id,
            $interview->organization_id,
            $interview->workspace_id,
            'recruitment.interview.outcome_recorded',
            'hr_candidate_interview',
            (string) $interview->id,
            ['decision' => $decision, 'rating' => $rating]
        );

        return $interview;
    }

    public function convertToEmployee(HrCandidate $candidate, array $employeeData, User $actor): HrEmployee
    {
        abort_unless($candidate->stage === 'hired', 422, 'Only hired candidates can be converted to employees.');

        return DB::transaction(function () use ($candidate, $employeeData, $actor): HrEmployee {
            $employee = HrEmployee::create([
                'organization_id' => $candidate->organization_id,
                'workspace_id' => $candidate->workspace_id,
                'user_id' => $employeeData['user_id'] ?? null,
                'branch_id' => $employeeData['branch_id'] ?? null,
                'department_id' => $employeeData['department_id'] ?? $candidate->position?->department_id,
                'designation_id' => $employeeData['designation_id'] ?? null,
                'shift_id' => $employeeData['shift_id'] ?? null,
                'employee_number' => $employeeData['employee_number'] ?? 'EMP-' . strtoupper(bin2hex(random_bytes(3))),
                'name' => $candidate->name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'joined_at' => $employeeData['joined_at'] ?? Carbon::today(),
                'basic_salary' => $employeeData['basic_salary'] ?? ($candidate->expected_salary ?? 0),
                'status' => 'active',
            ]);

            $candidate->update(['status' => 'converted']);

            $this->audit->log(
                $actor->id,
                $candidate->organization_id,
                $candidate->workspace_id,
                'recruitment.candidate.converted_to_employee',
                'hr_employee',
                (string) $employee->id,
                ['candidate_id' => $candidate->id, 'employee_number' => $employee->employee_number],
                critical: true
            );

            return $employee;
        });
    }

    public function getPipelineSummary(Workspace $ws, ?int $positionId = null): array
    {
        $query = HrCandidate::forWorkspace($ws->organization_id, $ws->id);
        if ($positionId) {
            $query->where('job_position_id', $positionId);
        }

        $stages = array_keys(self::STAGES);
        $counts = $query->select('stage', DB::raw('count(*) as aggregate'))
            ->groupBy('stage')
            ->pluck('aggregate', 'stage')
            ->toArray();

        $summary = [];
        foreach ($stages as $stage) {
            $summary[$stage] = (int) ($counts[$stage] ?? 0);
        }

        return $summary;
    }
}
