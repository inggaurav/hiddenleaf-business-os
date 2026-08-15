<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrFoxMissionStep extends Model
{
    use HasFactory;

    protected $table = 'mr_fox_mission_steps';

    protected $fillable = [
        'mission_id',
        'sequence',
        'tool_name',
        'input_params',
        'output_data',
        'status',
        'approval_proposal_id',
        'evidence',
        'observation',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'input_params' => 'array',
        'output_data' => 'array',
        'evidence' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(MrFoxMission::class, 'mission_id');
    }

    public function approvalProposal(): BelongsTo
    {
        return $this->belongsTo(MrFoxActionProposal::class, 'approval_proposal_id');
    }
}
