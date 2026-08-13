<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TasklyTask extends Model
{
    protected $table = 'taskly_tasks';

    protected $fillable = ['organization_id', 'workspace_id', 'project_id', 'stage_id', 'milestone_id', 'assigned_to', 'title', 'description', 'priority', 'due_on', 'estimated_hours', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'estimated_hours' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }
}
