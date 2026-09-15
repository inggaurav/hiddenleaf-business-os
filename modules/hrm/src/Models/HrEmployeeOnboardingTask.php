<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeOnboardingTask extends Model
{
    protected $table = 'hr_employee_onboarding_tasks';

    protected $fillable = [
        'onboarding_id',
        'onboarding_task_id',
        'title',
        'description',
        'category',
        'due_on',
        'status',
        'assigned_to',
        'completed_at',
        'completed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(HrEmployeeOnboarding::class, 'onboarding_id');
    }

    public function templateTask(): BelongsTo
    {
        return $this->belongsTo(HrOnboardingTask::class, 'onboarding_task_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
