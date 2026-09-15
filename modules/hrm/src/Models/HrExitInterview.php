<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrExitInterview extends Model
{
    protected $table = 'hr_exit_interviews';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'employee_id',
        'interview_date',
        'exit_reason',
        'would_return',
        'likes_about_company',
        'dislikes_about_company',
        'suggestions',
        'overall_rating',
        'conducted_by',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
            'overall_rating' => 'integer',
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

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }
}
