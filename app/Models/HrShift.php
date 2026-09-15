<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrShift extends Model
{
    protected $table = 'hr_shifts';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'name',
        'starts_at',
        'ends_at',
        'grace_minutes',
    ];

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'shift_id');
    }
}
