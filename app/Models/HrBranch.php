<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrBranch extends Model
{
    protected $table = 'hr_branches';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'name',
    ];

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(HrDepartment::class, 'branch_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'branch_id');
    }
}
