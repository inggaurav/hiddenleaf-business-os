<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationMessage extends Model
{
    use HasFactory;

    protected $table = 'comm_messages';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'conversation_id',
        'provider_message_id',
        'idempotency_key',
        'direction',
        'sender_name',
        'sender_identifier',
        'recipients',
        'body_text',
        'body_html',
        'delivery_status',
        'sent_at',
        'delivered_at',
        'read_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CommunicationConversation::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CommunicationAttachment::class, 'message_id');
    }
}
