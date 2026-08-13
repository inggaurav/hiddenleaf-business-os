<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIAgentChatSession extends Model
{
    use HasFactory;

    protected $table = 'ai_agent_chat_sessions';

    protected $fillable = [
        'title',
        'user_id',
        'workspace_id',
    ];

    public function messages()
    {
        return $this->hasMany(AIAgentChatMessage::class, 'session_id');
    }
}
