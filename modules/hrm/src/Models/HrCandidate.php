<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrCandidate extends Model
{
    protected $table = 'hr_candidates';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'job_position_id',
        'name',
        'email',
        'phone',
        'resume_path',
        'source',
        'current_company',
        'current_designation',
        'current_salary',
        'expected_salary',
        'notice_period_days',
        'available_from',
        'stage',
        'status',
        'notes',
        'assigned_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'current_salary' => 'decimal:2',
            'expected_salary' => 'decimal:2',
            'notice_period_days' => 'integer',
            'available_from' => 'date',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrJobPosition::class, 'job_position_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(HrCandidateInterview::class, 'candidate_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
