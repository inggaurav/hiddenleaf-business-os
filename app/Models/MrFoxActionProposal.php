<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrFoxActionProposal extends Model
{
    protected $table = 'mrfox_action_proposals';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'conversation_id',
        'tool_name',
        'payload',
        'payload_hash',
        'human_summary',
        'risk_level',
        'status',
        'requested_at',
        'approved_at',
        'approved_by',
        'executed_at',
        'result',
        'error',
        'error_trace',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
}
