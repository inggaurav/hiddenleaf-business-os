<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrFoxSocialInteraction extends Model
{
    use HasFactory;

    protected $table = 'mrfox_social_interactions';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'platform',
        'external_id',
        'sender_name',
        'sender_handle',
        'message_content',
        'sentiment',
        'priority',
        'status',
        'drafted_reply',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
