<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrFoxKnowledgeChunk extends Model
{
    use HasFactory;

    protected $table = 'mrfox_knowledge_chunks';

    protected $fillable = [
        'document_id',
        'organization_id',
        'workspace_id',
        'chunk_index',
        'page_number',
        'content',
        'token_count',
        'vector_id',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'page_number' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(MrFoxKnowledgeDocument::class, 'document_id');
    }
}
