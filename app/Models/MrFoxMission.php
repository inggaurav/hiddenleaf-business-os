<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MrFoxMission extends Model
{
    use HasFactory;

    protected $table = 'mr_fox_missions';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'brand_profile_id',
        'name',
        'objective',
        'status',
        'risk_level',
        'plan_json',
        'allowed_tools',
        'current_step',
        'progress_summary',
        'max_steps',
        'execution_budget_tokens',
        'used_tokens',
        'started_at',
        'paused_at',
        'completed_at',
        'expires_at',
        'lock_version',
    ];

    protected $casts = [
        'plan_json' => 'array',
        'allowed_tools' => 'array',
        'current_step' => 'integer',
        'max_steps' => 'integer',
        'execution_budget_tokens' => 'integer',
        'used_tokens' => 'integer',
        'lock_version' => 'integer',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brandProfile(): BelongsTo
    {
        return $this->belongsTo(MrFoxBrandProfile::class, 'brand_profile_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MrFoxMissionStep::class, 'mission_id')->orderBy('sequence');
    }
}
