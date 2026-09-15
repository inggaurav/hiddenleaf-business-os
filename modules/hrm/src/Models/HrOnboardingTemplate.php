<?php

namespace HiddenLeaf\Hrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrOnboardingTemplate extends Model
{
    protected $table = 'hr_onboarding_templates';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'title',
        'description',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(HrOnboardingTask::class, 'template_id')->orderBy('position');
    }
}
