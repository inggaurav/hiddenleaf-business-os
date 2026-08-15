<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MrFoxKnowledgeDocument extends Model
{
    use HasFactory;

    protected $table = 'mrfox_knowledge_documents';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'created_by',
        'title',
        'filename',
        'file_type',
        'file_path',
        'checksum',
        'visibility',
        'sensitivity_level',
        'version',
        'chunk_count',
        'total_tokens',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'version' => 'integer',
            'chunk_count' => 'integer',
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

    public function chunks(): HasMany
    {
        return $this->hasMany(MrFoxKnowledgeChunk::class, 'document_id');
    }
}
