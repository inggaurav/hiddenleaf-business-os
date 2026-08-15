<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationAttachment extends Model
{
    use HasFactory;

    protected $table = 'comm_attachments';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'message_id',
        'file_name',
        'mime_type',
        'file_size',
        'storage_disk',
        'file_path',
        'external_attachment_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunicationMessage::class, 'message_id');
    }
}
