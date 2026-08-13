<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistantSession extends Model
{
    use HasFactory;

    protected $table = 'ai_agent_chat_sessions';

    protected $fillable = ['title', 'user_id', 'workspace_id', 'provider', 'metadata', 'archived_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'archived_at' => 'datetime'];
    }

    public function messages()
    {
        return $this->hasMany(AssistantMessage::class, 'session_id');
    }
}
