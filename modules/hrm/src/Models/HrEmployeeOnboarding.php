<?php

namespace HiddenLeaf\Hrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrEmployeeOnboarding extends Model
{
    protected $table = 'hr_employee_onboardings';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'employee_id',
        'template_id',
        'status',
        'started_on',
        'target_completion_on',
        'completed_on',
        'assigned_buddy',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'target_completion_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrOnboardingTemplate::class, 'template_id');
    }

    public function buddy(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'assigned_buddy');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(HrEmployeeOnboardingTask::class, 'onboarding_id');
    }
}
