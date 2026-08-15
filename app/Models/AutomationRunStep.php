<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRunStep extends Model
{
    use HasFactory;

    protected $table = 'automation_run_steps';

    protected $fillable = [
        'run_id',
        'step_index',
        'action_name',
        'input_payload',
        'output_payload',
        'status',
        'approval_proposal_id',
        'retry_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'step_index' => 'integer',
        'input_payload' => 'array',
        'output_payload' => 'array',
        'retry_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }

    public function approvalProposal(): BelongsTo
    {
        return $this->belongsTo(MrFoxActionProposal::class, 'approval_proposal_id');
    }
}
