<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChMessage extends Model
{
    use HasFactory;

    protected $table = 'ch_messages';

    protected $fillable = [
        'from_id',
        'to_id',
        'body',
        'attachment',
        'seen',
        'workspace_id',
    ];

    protected $casts = [
        'seen' => 'boolean',
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_id');
    }
}
