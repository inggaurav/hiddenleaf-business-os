<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrExitClearance extends Model
{
    protected $table = 'hr_exit_clearances';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'employee_id',
        'department',
        'clearance_item',
        'status',
        'remarks',
        'cleared_by',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'cleared_at' => 'datetime',
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

    public function clearedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
