<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrLeaveType extends Model
{
    protected $table = 'hr_leave_types';

    protected $fillable = ['organization_id', 'workspace_id', 'name', 'annual_allowance', 'is_paid'];

    protected function casts(): array
    {
        return ['annual_allowance' => 'decimal:2', 'is_paid' => 'boolean'];
    }
}
