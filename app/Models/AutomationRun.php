<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    use HasFactory;

    protected $table = 'automation_runs';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'rule_id',
        'trigger_event',
        'trigger_payload',
        'status',
        'idempotency_key',
        'trace_id',
        'depth',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'depth' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationRunStep::class, 'run_id')->orderBy('step_index');
    }
}
