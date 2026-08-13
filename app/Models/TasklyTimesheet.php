<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TasklyTimesheet extends Model
{
    protected $table = 'taskly_timesheets';

    protected $fillable = ['organization_id', 'workspace_id', 'project_id', 'task_id', 'user_id', 'work_date', 'hours', 'description', 'status', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'hours' => 'decimal:2', 'approved_at' => 'datetime'];
    }
}
