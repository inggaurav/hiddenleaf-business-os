<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseDomain extends Model
{
    protected $fillable = ['license_id', 'domain', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
