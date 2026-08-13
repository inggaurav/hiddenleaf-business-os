<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpdateHistory extends Model
{
    protected $fillable = [
        'from_version',
        'to_version',
        'channel',
        'status',
        'manifest',
        'backup_path',
        'error_message',
        'started_by',
        'started_at',
        'completed_at',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }
}
