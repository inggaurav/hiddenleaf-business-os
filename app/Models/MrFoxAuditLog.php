<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrFoxAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'mrfox_audit_logs';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'conversation_id',
        'tool_name',
        'input_payload',
        'output_summary',
        'risk_level',
        'execution_status',
        'duration_ms',
        'provider',
        'model',
        'tokens_in',
        'tokens_out',
        'error',
        'created_at',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
}
