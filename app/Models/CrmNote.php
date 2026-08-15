<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmNote extends Model
{
    protected $table = 'crm_notes';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'subject_type',
        'subject_id',
        'body',
        'created_by',
    ];

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
