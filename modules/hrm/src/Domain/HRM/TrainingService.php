<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Models\User;
use App\Models\Workspace;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrTrainingEnrollment;
use HiddenLeaf\Hrm\Models\HrTrainingProgram;
use HiddenLeaf\Kernel\Services\AuditLogger;

class TrainingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function createProgram(Workspace $ws, array $data, User $actor): HrTrainingProgram
    {
        $program = HrTrainingProgram::create([
            'organization_id' => $ws->organization_id,
            'workspace_id' => $ws->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'technical',
            'mode' => $data['mode'] ?? 'in_person',
            'trainer_name' => $data['trainer_name'] ?? null,
            'trainer_contact' => $data['trainer_contact'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'cost_per_head' => $data['cost_per_head'] ?? 0,
            'status' => 'planned',
            'max_participants' => $data['max_participants'] ?? null,
            'location' => $data['location'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            $actor->id,
            $ws->organization_id,
            $ws->id,
            'training.program.created',
            'hr_training_program',
            (string) $program->id,
            ['title' => $program->title]
        );

        return $program;
    }

    public function enroll(HrTrainingProgram $program, HrEmployee $employee, User $actor): HrTrainingEnrollment
    {
        if ($program->max_participants !== null && $program->max_participants > 0) {
            $enrolledCount = $program->enrollments()->count();
            abort_unless($enrolledCount < $program->max_participants, 422, 'Training program has reached maximum participant capacity.');
        }

        $enrollment = HrTrainingEnrollment::create([
            'organization_id' => $program->organization_id,
            'workspace_id' => $program->workspace_id,
            'program_id' => $program->id,
            'employee_id' => $employee->id,
            'status' => 'enrolled',
        ]);

        $this->audit->log(
            $actor->id,
            $program->organization_id,
            $program->workspace_id,
            'training.employee.enrolled',
            'hr_training_enrollment',
            (string) $enrollment->id,
            ['program_id' => $program->id, 'employee_id' => $employee->id]
        );

        return $enrollment;
    }

    public function recordOutcome(HrTrainingEnrollment $enrollment, float $score, bool $passed, ?string $certPath, User $actor): HrTrainingEnrollment
    {
        $enrollment->update([
            'score' => $score,
            'passed' => $passed,
            'certificate_path' => $certPath,
            'status' => 'completed',
        ]);

        $this->audit->log(
            $actor->id,
            $enrollment->organization_id,
            $enrollment->workspace_id,
            'training.outcome.recorded',
            'hr_training_enrollment',
            (string) $enrollment->id,
            ['score' => $score, 'passed' => $passed]
        );

        return $enrollment;
    }

    public function getProgramReport(HrTrainingProgram $program): array
    {
        $enrolledCount = $program->enrollments()->count();
        $passedCount = $program->enrollments()->where('passed', true)->count();
        $avgScore = (float) ($program->enrollments()->whereNotNull('score')->avg('score') ?? 0);
        $completionRate = $enrolledCount > 0 ? round(($passedCount / $enrolledCount) * 100, 1) : 0.0;

        return [
            'enrolled_count' => $enrolledCount,
            'passed_count' => $passedCount,
            'avg_score' => round($avgScore, 2),
            'completion_rate' => $completionRate,
        ];
    }
}
