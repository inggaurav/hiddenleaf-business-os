<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationParticipant extends Model
{
    use HasFactory;

    protected $table = 'comm_participants';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'display_name',
        'email',
        'phone_number',
        'provider',
        'provider_user_id',
        'avatar_url',
        'linked_entity_type',
        'linked_entity_id',
    ];

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }
}
