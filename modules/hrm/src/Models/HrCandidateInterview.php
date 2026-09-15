<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrCandidateInterview extends Model
{
    protected $table = 'hr_candidate_interviews';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'candidate_id',
        'round_name',
        'interview_type',
        'scheduled_at',
        'duration_minutes',
        'location_or_link',
        'status',
        'interviewer_id',
        'rating',
        'feedback',
        'decision',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'rating' => 'integer',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(HrCandidate::class, 'candidate_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
