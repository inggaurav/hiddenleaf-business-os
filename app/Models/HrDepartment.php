<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDepartment extends Model
{
    protected $table = 'hr_departments';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'name',
        'branch_id',
    ];

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(HrBranch::class, 'branch_id');
    }

    public function designations(): HasMany
    {
        return $this->hasMany(HrDesignation::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'department_id');
    }
}
