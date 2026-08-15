<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasFactory;

    protected $table = 'automation_rules';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'name',
        'description',
        'enabled',
        'trigger_type',
        'trigger_config',
        'condition_config',
        'action_config',
        'created_by',
        'last_run_at',
        'next_run_at',
        'run_count',
        'failure_count',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'trigger_config' => 'array',
        'condition_config' => 'array',
        'action_config' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'run_count' => 'integer',
        'failure_count' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'rule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
