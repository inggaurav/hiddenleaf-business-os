<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplateLang extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'lang',
        'content',
    ];

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'parent_id');
    }
}
