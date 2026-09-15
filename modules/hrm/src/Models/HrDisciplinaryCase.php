<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDisciplinaryCase extends Model
{
    protected $table = 'hr_disciplinary_cases';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'employee_id',
        'case_number',
        'type',
        'severity',
        'incident_date',
        'incident_description',
        'action_taken',
        'action_notes',
        'action_date',
        'status',
        'handled_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'action_date' => 'date',
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

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
