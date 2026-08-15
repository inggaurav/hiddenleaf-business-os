<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrFoxUsageRecord extends Model
{
    public $timestamps = false;

    protected $table = 'mrfox_usage_records';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'provider',
        'model',
        'capability',
        'input_tokens',
        'output_tokens',
        'cost',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'cost' => 'decimal:6',
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
