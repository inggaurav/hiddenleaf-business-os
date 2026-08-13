<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'scope',
        'scope_id',
        'value',
        'is_public',
        'is_encrypted',
        'organization_id',
        'workspace_id',
        'user_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'is_encrypted' => 'boolean'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
