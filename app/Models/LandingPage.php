<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPage extends Model
{
    protected $fillable = ['site_id', 'slug', 'title', 'content', 'seo_title', 'seo_description', 'is_published', 'position'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
