<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'body',
        'variables',
        'workspace_id',
        'created_by',
    ];

    public function templateLangs()
    {
        return $this->hasMany(EmailTemplateLang::class, 'parent_id');
    }

    public function getLang(string $lang = 'en')
    {
        return $this->templateLangs()->where('lang', $lang)->first();
    }
}
