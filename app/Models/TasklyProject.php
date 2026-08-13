<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TasklyProject extends Model
{
    protected $table = 'taskly_projects';

    protected $fillable = ['organization_id', 'workspace_id', 'name', 'description', 'starts_on', 'due_on', 'budget', 'status', 'manager_id', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'due_on' => 'date', 'budget' => 'decimal:2'];
    }

    public function stages()
    {
        return $this->hasMany(TasklyStage::class, 'project_id')->orderBy('position');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'taskly_project_members', 'project_id', 'user_id')->withPivot(['hourly_rate', 'role']);
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }
}
