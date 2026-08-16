<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'module',
        'variables',
        'workspace_id',
        'is_enabled',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['variables' => 'array', 'is_enabled' => 'boolean'];
    }

    public function templateLangs()
    {
        return $this->hasMany(NotificationTemplateLang::class, 'parent_id');
    }
}
