<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingSection extends Model
{
    protected $fillable = ['site_id', 'type', 'heading', 'subheading', 'content', 'position', 'is_visible'];

    protected function casts(): array
    {
        return ['content' => 'array', 'is_visible' => 'boolean'];
    }
}
