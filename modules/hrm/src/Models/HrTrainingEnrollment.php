<?php

namespace HiddenLeaf\Hrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrTrainingEnrollment extends Model
{
    protected $table = 'hr_training_enrollments';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'program_id',
        'employee_id',
        'status',
        'score',
        'passed',
        'certificate_path',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'passed' => 'boolean',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(HrTrainingProgram::class, 'program_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
