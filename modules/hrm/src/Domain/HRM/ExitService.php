<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrExitClearance;
use HiddenLeaf\Hrm\Models\HrExitInterview;
use HiddenLeaf\Kernel\Services\AuditLogger;

class ExitService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function initiateExit(HrEmployee $employee, string $exitReason, DateTimeInterface $exitDate, User $actor): HrEmployee
    {
        $date = $exitDate instanceof Carbon ? $exitDate : Carbon::instance($exitDate);
        $employee->update([
            'exit_reason' => $exitReason,
            'exit_date' => $date,
        ]);

        $this->audit->log(
            $actor->id,
            $employee->organization_id,
            $employee->workspace_id,
            'exit.initiated',
            'hr_employee',
            (string) $employee->id,
            ['exit_reason' => $exitReason, 'exit_date' => $date->toDateString()],
            critical: true
        );

        return $employee;
    }

    public function conductExitInterview(HrEmployee $employee, array $data, User $actor): HrExitInterview
    {
        abort_if(HrExitInterview::where('employee_id', $employee->id)->exists(), 422, 'Exit interview already exists for this employee.');

        $interview = HrExitInterview::create([
            'organization_id' => $employee->organization_id,
            'workspace_id' => $employee->workspace_id,
            'employee_id' => $employee->id,
            'interview_date' => $data['interview_date'] ?? Carbon::today(),
            'exit_reason' => $data['exit_reason'] ?? ($employee->exit_reason ?? 'Personal Reasons'),
            'would_return' => $data['would_return'] ?? 'maybe',
            'likes_about_company' => $data['likes_about_company'] ?? null,
            'dislikes_about_company' => $data['dislikes_about_company'] ?? null,
            'suggestions' => $data['suggestions'] ?? null,
            'overall_rating' => $data['overall_rating'] ?? null,
            'conducted_by' => $actor->id,
        ]);

        $this->audit->log(
            $actor->id,
            $employee->organization_id,
            $employee->workspace_id,
            'exit.interview.conducted',
            'hr_exit_interview',
            (string) $interview->id,
            ['employee_id' => $employee->id]
        );

        return $interview;
    }

    public function addClearanceItems(HrEmployee $employee, array $items, User $actor): array
    {
        $created = [];
        foreach ($items as $item) {
            $clearance = HrExitClearance::create([
                'organization_id' => $employee->organization_id,
                'workspace_id' => $employee->workspace_id,
                'employee_id' => $employee->id,
                'department' => $item['department'],
                'clearance_item' => $item['clearance_item'],
                'status' => 'pending',
            ]);
            $created[] = $clearance;
        }

        $this->audit->log(
            $actor->id,
            $employee->organization_id,
            $employee->workspace_id,
            'exit.clearance_items.added',
            'hr_exit_clearance',
            null,
            ['employee_id' => $employee->id, 'count' => count($created)]
        );

        return $created;
    }

    public function clearItem(HrExitClearance $clearance, User $actor, ?string $remarks = null): HrExitClearance
    {
        $clearance->update([
            'status' => 'cleared',
            'cleared_by' => $actor->id,
            'cleared_at' => Carbon::now(),
            'remarks' => $remarks ?? $clearance->remarks,
        ]);

        $this->audit->log(
            $actor->id,
            $clearance->organization_id,
            $clearance->workspace_id,
            'exit.clearance_item.cleared',
            'hr_exit_clearance',
            (string) $clearance->id,
            ['item' => $clearance->clearance_item]
        );

        return $clearance;
    }

    public function isFullyCleared(HrEmployee $employee): bool
    {
        $clearances = HrExitClearance::where('employee_id', $employee->id)->get();
        if ($clearances->isEmpty()) {
            return true;
        }

        return $clearances->every(fn (HrExitClearance $c): bool => $c->status === 'cleared');
    }

    public function completeExit(HrEmployee $employee, User $actor): HrEmployee
    {
        abort_unless($this->isFullyCleared($employee), 422, 'Cannot complete exit: employee has pending clearance items.');

        $employee->update([
            'status' => 'terminated',
            'ended_at' => $employee->exit_date ?? Carbon::today(),
        ]);

        $this->audit->log(
            $actor->id,
            $employee->organization_id,
            $employee->workspace_id,
            'exit.completed',
            'hr_employee',
            (string) $employee->id,
            ['ended_at' => $employee->ended_at?->toDateString()],
            critical: true
        );

        return $employee;
    }
}
