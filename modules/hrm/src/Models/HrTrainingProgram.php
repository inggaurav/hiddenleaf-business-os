<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrTrainingProgram extends Model
{
    protected $table = 'hr_training_programs';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'title',
        'description',
        'category',
        'mode',
        'trainer_name',
        'trainer_contact',
        'starts_on',
        'ends_on',
        'duration_hours',
        'cost_per_head',
        'status',
        'max_participants',
        'location',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'duration_hours' => 'integer',
            'cost_per_head' => 'decimal:2',
            'max_participants' => 'integer',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(HrTrainingEnrollment::class, 'program_id');
    }
}
