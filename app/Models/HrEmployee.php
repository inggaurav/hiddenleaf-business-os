<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HrEmployee extends Model
{
    protected $table = 'hr_employees';

    protected $fillable = ['organization_id', 'workspace_id', 'user_id', 'branch_id', 'department_id', 'designation_id', 'shift_id', 'employee_number', 'name', 'email', 'joined_at', 'ended_at', 'basic_salary', 'status'];

    protected function casts(): array
    {
        return ['joined_at' => 'date', 'ended_at' => 'date', 'basic_salary' => 'decimal:2'];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }
}
