<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = ['addon_id', 'alias', 'name', 'version', 'minimum_core', 'dependencies', 'manifest', 'status'];

    protected function casts(): array
    {
        return ['dependencies' => 'array', 'manifest' => 'array'];
    }
}
