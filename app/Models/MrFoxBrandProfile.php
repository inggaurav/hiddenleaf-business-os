<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrFoxBrandProfile extends Model
{
    use HasFactory;

    protected $table = 'mrfox_brand_profiles';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'created_by',
        'name',
        'industry',
        'tagline',
        'mission',
        'tone_of_voice',
        'target_audience',
        'value_propositions',
        'compliance_guidelines',
        'visual_direction',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'tone_of_voice' => 'array',
            'target_audience' => 'array',
            'value_propositions' => 'array',
            'compliance_guidelines' => 'array',
            'visual_direction' => 'array',
            'is_default' => 'boolean',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
