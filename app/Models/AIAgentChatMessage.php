<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIAgentChatMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_agent_chat_messages';

    protected $fillable = [
        'session_id',
        'role',
        'message',
    ];

    public function session()
    {
        return $this->belongsTo(AIAgentChatSession::class, 'session_id');
    }
}
