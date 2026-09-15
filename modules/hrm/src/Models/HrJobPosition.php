<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\HrDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrJobPosition extends Model
{
    protected $table = 'hr_job_positions';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'department_id',
        'title',
        'description',
        'requirements',
        'employment_type',
        'location',
        'salary_min',
        'salary_max',
        'openings',
        'status',
        'posted_on',
        'closes_on',
        'hiring_manager_id',
    ];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'openings' => 'integer',
            'posted_on' => 'date',
            'closes_on' => 'date',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(HrCandidate::class, 'job_position_id');
    }
}
