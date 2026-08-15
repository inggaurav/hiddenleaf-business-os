<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MrFoxConversation extends Model
{
    use HasFactory;

    protected $table = 'mrfox_conversations';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'title',
        'active_page',
        'is_archived',
        'total_tokens',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'total_tokens' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MrFoxMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }
}
