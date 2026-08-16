<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'module',
        'subject',
        'body',
        'variables',
        'is_enabled',
        'workspace_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['variables' => 'array', 'is_enabled' => 'boolean'];
    }

    public function templateLangs()
    {
        return $this->hasMany(EmailTemplateLang::class, 'parent_id');
    }

    public function getLang(string $lang = 'en')
    {
        return $this->templateLangs()->where('lang', $lang)->first();
    }
}
