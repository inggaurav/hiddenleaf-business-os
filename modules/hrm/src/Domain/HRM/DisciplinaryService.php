<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Models\User;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrDisciplinaryCase;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Kernel\Services\AuditLogger;

class DisciplinaryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function openCase(HrEmployee $employee, array $data, User $actor): HrDisciplinaryCase
    {
        $year = Carbon::now()->format('Y');
        $count = HrDisciplinaryCase::where('workspace_id', $employee->workspace_id)
            ->whereYear('created_at', $year)
            ->count() + 1;
        $caseNumber = sprintf('DISC-%s-%03d', $year, $count);

        $case = HrDisciplinaryCase::create([
            'organization_id' => $employee->organization_id,
            'workspace_id' => $employee->workspace_id,
            'employee_id' => $employee->id,
            'case_number' => $caseNumber,
            'type' => $data['type'],
            'severity' => $data['severity'] ?? 'minor',
            'incident_date' => $data['incident_date'],
            'incident_description' => $data['incident_description'],
            'status' => 'open',
            'handled_by' => $data['handled_by'] ?? $actor->id,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            $actor->id,
            $employee->organization_id,
            $employee->workspace_id,
            'disciplinary.case.opened',
            'hr_disciplinary_case',
            (string) $case->id,
            ['case_number' => $caseNumber, 'employee_id' => $employee->id],
            critical: true
        );

        return $case;
    }

    public function recordAction(HrDisciplinaryCase $case, string $actionTaken, string $notes, User $actor): HrDisciplinaryCase
    {
        abort_if($case->status === 'closed', 422, 'Cannot record action on a closed disciplinary case.');

        $case->update([
            'action_taken' => $actionTaken,
            'action_notes' => $notes,
            'action_date' => Carbon::today(),
        ]);

        $this->audit->log(
            $actor->id,
            $case->organization_id,
            $case->workspace_id,
            'disciplinary.action.recorded',
            'hr_disciplinary_case',
            (string) $case->id,
            ['action_taken' => $actionTaken],
            critical: true
        );

        return $case;
    }

    public function closeCase(HrDisciplinaryCase $case, User $actor): HrDisciplinaryCase
    {
        $case->update(['status' => 'closed']);

        $this->audit->log(
            $actor->id,
            $case->organization_id,
            $case->workspace_id,
            'disciplinary.case.closed',
            'hr_disciplinary_case',
            (string) $case->id,
            ['case_number' => $case->case_number],
            critical: true
        );

        return $case;
    }

    public function reopenCase(HrDisciplinaryCase $case, User $actor): HrDisciplinaryCase
    {
        abort_unless($case->status === 'closed', 422, 'Only closed disciplinary cases can be reopened.');

        $case->update(['status' => 'open']);

        $this->audit->log(
            $actor->id,
            $case->organization_id,
            $case->workspace_id,
            'disciplinary.case.reopened',
            'hr_disciplinary_case',
            (string) $case->id,
            ['case_number' => $case->case_number],
            critical: true
        );

        return $case;
    }
}
