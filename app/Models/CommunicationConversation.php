<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationConversation extends Model
{
    use HasFactory;

    protected $table = 'comm_conversations';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'account_id',
        'provider',
        'external_thread_id',
        'subject',
        'participant_name',
        'participant_identifier',
        'last_message_preview',
        'last_message_at',
        'unread_count',
        'status',
        'priority_score',
        'sentiment',
        'intent',
        'assigned_to',
        'linked_entity_type',
        'linked_entity_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'unread_count' => 'integer',
            'priority_score' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CommunicationAccount::class, 'account_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'conversation_id');
    }
}
