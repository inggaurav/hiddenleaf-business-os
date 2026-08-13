<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistantMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_agent_chat_messages';

    protected $fillable = ['session_id', 'role', 'message', 'provider', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function session()
    {
        return $this->belongsTo(AssistantSession::class, 'session_id');
    }
}
